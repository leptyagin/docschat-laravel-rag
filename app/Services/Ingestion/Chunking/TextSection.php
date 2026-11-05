<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Chunking;

final readonly class TextSection
{
    /**
     * @param  string|null  $heading  The heading trail, such as "Parking > Harbor View".
     * @param  list<TextBlock>  $blocks
     */
    public function __construct(
        public ?string $heading,
        public array $blocks,
    ) {}
}
