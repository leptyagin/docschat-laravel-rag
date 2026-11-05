<?php

declare(strict_types=1);

namespace App\Data\Answer;

use App\Models\Chunk;

final readonly class RetrievedChunk
{
    /**
     * @param  float|null  $similarity  Cosine similarity, or null for a full-text match only.
     * @param  float  $score  The Reciprocal Rank Fusion score.
     */
    public function __construct(
        public Chunk $chunk,
        public ?float $similarity,
        public float $score,
    ) {}
}
