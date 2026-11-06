<?php

declare(strict_types=1);

namespace App\Ai\Contracts;

interface ModerationModel
{
    /**
     * Whether a text breaks the provider's usage policies.
     */
    public function isFlagged(string $text): bool;
}
