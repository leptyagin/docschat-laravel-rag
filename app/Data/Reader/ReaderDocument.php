<?php

declare(strict_types=1);

namespace App\Data\Reader;

use Carbon\CarbonInterface;

final readonly class ReaderDocument
{
    /**
     * @param  list<ReaderSection>  $sections
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $typeLabel,
        public ?CarbonInterface $updatedAt,
        public int $readingMinutes,
        public ?int $pageCount,
        public int $passageCount,
        public string $originalUrl,
        public array $sections,
    ) {}

    /**
     * The sections worth listing in the navigation.
     *
     * @return list<ReaderSection>
     */
    public function outline(): array
    {
        return array_values(array_filter(
            $this->sections,
            fn (ReaderSection $section): bool => $section->level === 1 && ! $section->isLead(),
        ));
    }
}
