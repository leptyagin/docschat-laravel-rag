<?php

declare(strict_types=1);

namespace App\Data\Ingestion;

final readonly class ChunkDraft
{
    public function __construct(
        public int $position,
        public ?string $heading,
        public ?int $page,
        public string $content,
        public int $tokenCount,
    ) {}
}
