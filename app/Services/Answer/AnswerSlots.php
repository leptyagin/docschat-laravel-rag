<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Data\Answer\AnswerSlot;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use LogicException;

/**
 * Limits how many answers run at once, overall and per network. A streamed answer
 * holds a web server thread, so without this a few requests could take them all.
 */
final readonly class AnswerSlots
{
    public function __construct(
        private Cache $cache,
        #[Config('docschat.answers.max_concurrent')] private int $maxConcurrent,
        #[Config('docschat.answers.max_concurrent_per_network')] private int $maxPerNetwork,
        #[Config('docschat.answers.slot_seconds')] private int $slotSeconds,
    ) {}

    /**
     * @return AnswerSlot|null Null when no slot is free.
     */
    public function acquire(string $network): ?AnswerSlot
    {
        $networkLock = $this->firstFreeLock("docschat:answer-slot:{$network}", $this->maxPerNetwork);

        if ($networkLock === null) {
            return null;
        }

        $globalLock = $this->firstFreeLock('docschat:answer-slot:all', $this->maxConcurrent);

        if ($globalLock === null) {
            $networkLock->release();

            return null;
        }

        return new AnswerSlot([$networkLock, $globalLock]);
    }

    private function firstFreeLock(string $prefix, int $slots): ?Lock
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            throw new LogicException('The cache store must support locks.');
        }

        for ($slot = 1; $slot <= $slots; $slot++) {
            $lock = $store->lock("{$prefix}:{$slot}", $this->slotSeconds);

            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }
}
