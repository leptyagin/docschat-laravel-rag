<?php

declare(strict_types=1);

namespace App\Data\Answer;

/**
 * A share of the budget held for an answer that is being generated.
 */
final readonly class BudgetReservation
{
    public function __construct(
        public string $key,
        public int $microdollars,
    ) {}
}
