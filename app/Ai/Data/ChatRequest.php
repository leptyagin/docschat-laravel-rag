<?php

declare(strict_types=1);

namespace App\Ai\Data;

final readonly class ChatRequest
{
    /**
     * @param  string|null  $model  Overrides the configured chat model.
     * @param  string|null  $endUser  A pseudonym of the visitor, so the provider can trace abuse to one visitor rather than to the whole API key.
     */
    public function __construct(
        public string $systemPrompt,
        public string $prompt,
        public int $maxTokens,
        public ?string $model = null,
        public ?string $endUser = null,
    ) {}
}
