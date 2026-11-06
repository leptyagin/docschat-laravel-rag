<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Ai\Contracts\ChatModel;
use App\Ai\Data\ChatRequest;
use App\Data\Answer\ModelUsage;
use App\Data\Answer\RewrittenQuery;
use App\Enums\MessageRole;
use App\Models\Message;
use App\Services\Ingestion\Chunking\EstimatedTokenCounter;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Str;
use Throwable;

final readonly class QueryRewriter
{
    private const int BOUNDARY_LENGTH = 16;

    public function __construct(
        private ChatModel $chat,
        private PromptLibrary $prompts,
        private EstimatedTokenCounter $tokenCounter,
        #[Config('docschat.ai.chat.rewrite_model')] private string $model,
        #[Config('docschat.ai.chat.max_rewrite_tokens')] private int $maxTokens,
        #[Config('docschat.answers.max_question_length')] private int $maxQueryLength,
    ) {}

    /**
     * A first message has nothing to resolve, so it skips the model entirely.
     * If rewriting fails, the original question is still a usable search query,
     * and the attempt is estimated, because the provider may have billed it.
     *
     * The visitor writes both the conversation and the message, so they travel in
     * blocks with a random suffix, and the model's reply is cut down to one query.
     *
     * @param  list<Message>  $history  Oldest first.
     */
    public function rewrite(string $question, array $history, ?string $endUser = null): RewrittenQuery
    {
        if ($history === []) {
            return new RewrittenQuery($question);
        }

        $boundary = Str::random(self::BOUNDARY_LENGTH);
        $systemPrompt = $this->prompts->render('rewrite', ['boundary' => $boundary]);
        $prompt = $this->conversationPrompt($question, $history, $boundary);

        try {
            $response = $this->chat->generate(new ChatRequest(
                systemPrompt: $systemPrompt,
                prompt: $prompt,
                maxTokens: $this->maxTokens,
                model: $this->model,
                endUser: $endUser,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return new RewrittenQuery($question, new ModelUsage($this->model, $this->tokenCounter->count($systemPrompt.$prompt)));
        }

        $query = Str::limit(trim(strtok(trim($response->text), "\r\n") ?: ''), $this->maxQueryLength, '');

        return new RewrittenQuery(
            query: $query === '' ? $question : $query,
            usage: new ModelUsage($response->model, $response->promptTokens, $response->completionTokens),
        );
    }

    /**
     * @param  list<Message>  $history
     */
    private function conversationPrompt(string $question, array $history, string $boundary): string
    {
        $turns = array_map(
            fn (Message $message): string => ($message->role === MessageRole::User ? 'Visitor: ' : 'Assistant: ').$message->content,
            $history,
        );

        return "<conversation-{$boundary}>\n".implode("\n", $turns)."\n</conversation-{$boundary}>\n\n"
            ."<latest-message-{$boundary}>\n{$question}\n</latest-message-{$boundary}>";
    }
}
