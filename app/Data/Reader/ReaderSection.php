<?php

declare(strict_types=1);

namespace App\Data\Reader;

final readonly class ReaderSection
{
    /**
     * @param  string|null  $title  Null for the text before the first heading.
     * @param  int  $level  1 for a top-level section of the document.
     * @param  list<ReaderPassage>  $passages  Empty for a heading that only groups subsections.
     */
    public function __construct(
        public string $anchor,
        public ?string $title,
        public int $level,
        public array $passages,
    ) {}

    public function isLead(): bool
    {
        return $this->title === null;
    }

    /**
     * The heading element for this depth. The document title is the only h1.
     */
    public function headingTag(): string
    {
        return 'h'.min($this->level + 1, 4);
    }
}
