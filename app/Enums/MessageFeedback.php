<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageFeedback: string
{
    case Up = 'up';
    case Down = 'down';
}
