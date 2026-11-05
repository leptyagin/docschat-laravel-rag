<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Ai\Data\ChatRequest;
use App\Data\Answer\RetrievedChunk;
use App\Models\Bot;
use Illuminate\Container\Attributes\Config;

final readonly class AnswerPromptBuilder
{
    public function __construct(
        private PromptLibrary $prompts,
        #[Config('docschat.answers.max_answer_tokens')] private int $maxTokens,
    ) {}

    /**
     * Only the standalone query carries the conversation into this request.
     * Earlier answers are left out, so the model cannot cite its own past text
     * instead of the documents, and each answer costs the same to generate.
     *
     * @param  list<RetrievedChunk>  $chunks
     */
    public function build(Bot $bot, string $question, string $searchQuery, array $chunks): ChatRequest
    {
        return new ChatRequest(
            systemPrompt: $this->prompts->render('answer', [
                'bot_name' => $bot->name,
                'fallback_contact' => $bot->fallback_contact,
            ]),
            prompt: $this->userPrompt($question, $searchQuery, $chunks),
            maxTokens: $this->maxTokens,
        );
    }

    /**
     * @param  list<RetrievedChunk>  $chunks
     */
    private function userPrompt(string $question, string $searchQuery, array $chunks): string
    {
        $items = [];

        foreach ($chunks as $index => $retrieved) {
            $items[] = $this->contextItem($index + 1, $retrieved);
        }

        $prompt = "<context>\n".implode("\n", $items)."\n</context>\n\nQuestion: ".$question;

        return $searchQuery === $question ? $prompt : $prompt."\nThe question in full: ".$searchQuery;
    }

    private function contextItem(int $number, RetrievedChunk $retrieved): string
    {
        $chunk = $retrieved->chunk;

        $attributes = sprintf(
            'number="%d" document="%s" section="%s"',
            $number,
            e($chunk->document->title),
            e((string) $chunk->heading),
        );

        return "<item {$attributes}>\n{$this->neutralize($chunk->content)}\n</item>";
    }

    /**
     * Document text must not be able to close its own item and pose as part of
     * the prompt, so anything resembling these tags is defused.
     */
    private function neutralize(string $content): string
    {
        return (string) preg_replace('/<(\/?)(context|item)\b/i', '< $1$2', $content);
    }
}
