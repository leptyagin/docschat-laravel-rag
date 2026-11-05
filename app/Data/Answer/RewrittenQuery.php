<?php

declare(strict_types=1);

namespace App\Data\Answer;

final readonly class RewrittenQuery
{
    /**
     * @param  ModelUsage|null  $usage  Null when the model was not needed.
     */
    public function __construct(
        public string $query,
        public ?ModelUsage $usage = null,
    ) {}
}
