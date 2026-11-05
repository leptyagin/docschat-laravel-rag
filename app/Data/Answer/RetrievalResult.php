<?php

declare(strict_types=1);

namespace App\Data\Answer;

final readonly class RetrievalResult
{
    /**
     * @param  list<RetrievedChunk>  $chunks  Best first.
     * @param  float|null  $bestSimilarity  Taken from all candidates, not only the kept chunks.
     */
    public function __construct(
        public array $chunks,
        public ?float $bestSimilarity,
        public ModelUsage $usage,
    ) {}
}
