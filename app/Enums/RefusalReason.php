<?php

declare(strict_types=1);

namespace App\Enums;

enum RefusalReason: string
{
    case LowSimilarity = 'low_similarity';
    case NoCitation = 'no_citation';
    case ModelError = 'model_error';
}
