<?php

declare(strict_types=1);

namespace App\Enums;

enum AdminRole: string
{
    case Admin = 'admin';
    case Demo = 'demo';
}
