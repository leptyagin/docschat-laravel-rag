<?php

declare(strict_types=1);

namespace App\Enums;

enum RefusalReason: string
{
    case LowSimilarity = 'low_similarity';
    case NoCitation = 'no_citation';
    case ModelError = 'model_error';
    case BudgetExceeded = 'budget_exceeded';
    case Flagged = 'flagged';

    /**
     * Whether the documents could not answer, as opposed to the service being down.
     */
    public function isUnanswered(): bool
    {
        return $this === self::LowSimilarity || $this === self::NoCitation;
    }
}
