<?php

declare(strict_types=1);

namespace App\Ai\Data;

final readonly class ChatResponse
{
    public function __construct(
        public string $text,
        public string $model,
        public int $promptTokens,
        public int $completionTokens,
    ) {}
}
