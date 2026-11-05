<?php

declare(strict_types=1);

namespace App\Data\Answer;

use App\Enums\RefusalReason;

/**
 * What is known about an answer so far. It is filled in step by step, so that
 * a failure halfway still leaves an accurate record of what was spent.
 */
final class AnswerDraft
{
    public string $text = '';

    public ?string $model = null;

    public ?RefusalReason $refusalReason = null;

    /** @var list<Source> */
    public array $sources = [];

    /** @var list<ModelUsage> */
    public array $usages = [];

    public function promptTokens(): int
    {
        return array_sum(array_map(fn (ModelUsage $usage): int => $usage->promptTokens, $this->usages));
    }

    public function completionTokens(): int
    {
        return array_sum(array_map(fn (ModelUsage $usage): int => $usage->completionTokens, $this->usages));
    }
}
