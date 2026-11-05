<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function isInFlight(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }
}
