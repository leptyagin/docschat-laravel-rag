<?php

declare(strict_types=1);

namespace App\Data\Ingestion;

final readonly class ExtractedPage
{
    /**
     * @param  string  $text  Markdown-flavoured text: headings are marked with "#".
     * @param  int|null  $number  The page number, for sources that have pages.
     */
    public function __construct(
        public string $text,
        public ?int $number = null,
    ) {}
}
