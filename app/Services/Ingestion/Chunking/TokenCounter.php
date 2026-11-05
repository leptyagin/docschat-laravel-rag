<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Chunking;

interface TokenCounter
{
    public function count(string $text): int;
}
