<?php

declare(strict_types=1);

namespace App\Ai\Fakes;

use App\Ai\Contracts\ModerationModel;

/**
 * Lets every text through, for running without a provider or with moderation off.
 */
final class FakeModerationModel implements ModerationModel
{
    public function isFlagged(string $text): bool
    {
        return false;
    }
}
