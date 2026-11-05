<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Chunking;

final readonly class TextBlock
{
    public function __construct(
        public string $text,
        public ?int $page,
    ) {}
}
