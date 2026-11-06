<?php

declare(strict_types=1);

namespace App\Data\Answer;

use Illuminate\Contracts\Cache\Lock;

/**
 * The right to generate one answer. The locks expire by themselves, so a process
 * that dies while holding them cannot lose a slot for good.
 */
final readonly class AnswerSlot
{
    /**
     * @param  list<Lock>  $locks
     */
    public function __construct(private array $locks) {}

    public function release(): void
    {
        foreach ($this->locks as $lock) {
            $lock->release();
        }
    }
}
