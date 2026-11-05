<?php

declare(strict_types=1);

namespace App\Data\Ingestion;

final readonly class TextBlock
{
    public function __construct(
        public string $text,
        public ?int $page,
    ) {}
}
