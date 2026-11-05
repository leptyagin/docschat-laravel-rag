<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentSourceType: string
{
    case Pdf = 'pdf';
    case Url = 'url';
    case Text = 'text';
}
