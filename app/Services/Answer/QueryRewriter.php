<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Ai\Contracts\ChatModel;
use App\Ai\Data\ChatRequest;
use App\Data\Answer\ModelUsage;
use App\Data\Answer\RewrittenQuery;
use App\Enums\MessageRole;
use App\Models\Message;
use Illuminate\Container\Attributes\Config;
use Throwable;

final readonly class QueryRewriter
{
    public function __construct(
        private ChatModel $chat,
        private PromptLibrary $prompts,
        #[Config('docschat.ai.chat.rewrite_model')] private string $model,
        #[Config('docschat.answers.max_rewrite_tokens')] private int $maxTokens,
    ) {}

    /**
     * A first message has nothing to resolve, so it skips the model entirely.
     * If rewriting fails, the original question is still a usable search query.
     *
     * @param  list<Message>  $history  Oldest first.
     */
    public function rewrite(string $question, array $history): RewrittenQuery
    {
        if ($history === []) {
            return new RewrittenQuery($question);
        }

        try {
            $response = $this->chat->generate(new ChatRequest(
                systemPrompt: $this->prompts->render('rewrite'),
                prompt: $this->conversationPrompt($question, $history),
                maxTokens: $this->maxTokens,
                model: $this->model,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return new RewrittenQuery($question);
        }

        $query = trim($response->text);

        return new RewrittenQuery(
            query: $query === '' ? $question : $query,
            usage: new ModelUsage($response->model, $response->promptTokens, $response->completionTokens),
        );
    }

    /**
     * @param  list<Message>  $history
     */
    private function conversationPrompt(string $question, array $history): string
    {
        $turns = array_map(
            fn (Message $message): string => ($message->role === MessageRole::User ? 'Visitor: ' : 'Assistant: ').$message->content,
            $history,
        );

        return "Conversation:\n".implode("\n", $turns)."\n\nLatest message: ".$question;
    }
}
