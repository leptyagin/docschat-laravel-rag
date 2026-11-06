<?php

declare(strict_types=1);

namespace App\Data\Reader;

/**
 * One stored chunk, ready to show. Answers link to a passage by its position.
 */
final readonly class ReaderPassage
{
    /**
     * @param  int|null  $startsPage  The PDF page that begins here, if a new one does.
     * @param  string  $html  Sanitized by PassageRenderer.
     */
    public function __construct(
        public int $position,
        public ?int $startsPage,
        public string $html,
    ) {}

    public function anchor(): string
    {
        return self::anchorFor($this->position);
    }

    public static function anchorFor(int $position): string
    {
        return "passage-{$position}";
    }
}
