<?php

declare(strict_types=1);

namespace App\Data\Answer;

final readonly class ModelUsage
{
    public function __construct(
        public string $model,
        public int $promptTokens,
        public int $completionTokens = 0,
    ) {}
}
