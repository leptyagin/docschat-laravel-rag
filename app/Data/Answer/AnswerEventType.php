<?php

declare(strict_types=1);

namespace App\Data\Answer;

enum AnswerEventType: string
{
    case Started = 'started';
    case Delta = 'delta';
    case Completed = 'completed';
}
