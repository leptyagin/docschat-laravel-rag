<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Ai\Data\ChatRequest;
use App\Data\Answer\RetrievedChunk;
use App\Models\Bot;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Str;

final readonly class AnswerPromptBuilder
{
    private const int BOUNDARY_LENGTH = 16;

    public function __construct(
        private PromptLibrary $prompts,
        #[Config('docschat.answers.max_answer_tokens')] private int $maxTokens,
    ) {}

    /**
     * Only the standalone query carries the conversation into this request.
     * Earlier answers are left out, so the model cannot cite its own past text
     * instead of the documents, and each answer costs the same to generate.
     *
     * Every block is closed by a tag with a random suffix, made for this request.
     * Neither a document nor a visitor can guess it, so nothing they write can
     * close a block and pose as part of the prompt.
     *
     * @param  list<RetrievedChunk>  $chunks
     */
    public function build(Bot $bot, string $question, string $searchQuery, array $chunks, ?string $endUser = null): ChatRequest
    {
        $boundary = Str::random(self::BOUNDARY_LENGTH);

        return new ChatRequest(
            systemPrompt: $this->prompts->render('answer', [
                'bot_name' => $bot->name,
                'fallback_contact' => $bot->fallback_contact,
                'boundary' => $boundary,
            ]),
            prompt: $this->userPrompt($question, $searchQuery, $chunks, $boundary),
            maxTokens: $this->maxTokens,
            endUser: $endUser,
        );
    }

    /**
     * @param  list<RetrievedChunk>  $chunks
     */
    private function userPrompt(string $question, string $searchQuery, array $chunks, string $boundary): string
    {
        $items = [];

        foreach ($chunks as $index => $retrieved) {
            $items[] = $this->contextItem($index + 1, $retrieved, $boundary);
        }

        $questionBlock = $searchQuery === $question ? $question : "{$question}\nThe question in full: {$searchQuery}";

        return "<context-{$boundary}>\n".implode("\n", $items)."\n</context-{$boundary}>\n\n"
            ."<question-{$boundary}>\n{$questionBlock}\n</question-{$boundary}>";
    }

    private function contextItem(int $number, RetrievedChunk $retrieved, string $boundary): string
    {
        $chunk = $retrieved->chunk;

        $attributes = sprintf(
            'number="%d" document="%s" section="%s"',
            $number,
            e($chunk->document->title),
            e((string) $chunk->heading),
        );

        return "<item-{$boundary} {$attributes}>\n{$chunk->content}\n</item-{$boundary}>";
    }
}
