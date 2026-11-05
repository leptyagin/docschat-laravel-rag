<?php

declare(strict_types=1);

namespace App\Ai\Data;

final readonly class ChatRequest
{
    /**
     * @param  string|null  $model  Overrides the configured chat model.
     */
    public function __construct(
        public string $systemPrompt,
        public string $prompt,
        public int $maxTokens,
        public ?string $model = null,
    ) {}
}
