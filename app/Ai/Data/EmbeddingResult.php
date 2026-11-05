<?php

declare(strict_types=1);

namespace App\Ai\Data;

final readonly class EmbeddingResult
{
    /**
     * @param  list<list<float>>  $vectors
     */
    public function __construct(
        public array $vectors,
        public string $model,
        public int $promptTokens,
    ) {}
}
