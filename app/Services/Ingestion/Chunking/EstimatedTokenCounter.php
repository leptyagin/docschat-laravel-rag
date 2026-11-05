<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Chunking;

/**
 * Approximates tokens as four bytes of UTF-8. Counting bytes rather than
 * characters keeps the estimate honest for non-Latin scripts, which take more
 * tokens per character. Chunk sizes are a target, so a tokenizer is not needed.
 */
final class EstimatedTokenCounter implements TokenCounter
{
    private const int BYTES_PER_TOKEN = 4;

    public function count(string $text): int
    {
        return (int) ceil(strlen($text) / self::BYTES_PER_TOKEN);
    }
}
