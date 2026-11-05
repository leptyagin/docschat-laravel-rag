<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Enums\MessageRole;
use App\Models\Message;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * A cap on what the public demo may spend per day across all bots. The total is
 * cached briefly, so a burst can overshoot by a few answers, each a fraction of
 * a cent, in exchange for not summing the day's messages on every question.
 */
final readonly class DailyBudget
{
    private const int CACHE_SECONDS = 30;

    public function __construct(
        private Cache $cache,
        #[Config('docschat.daily_budget_usd')] private float $limitUsd,
    ) {}

    public function isExhausted(): bool
    {
        return $this->spentToday() >= $this->limitUsd;
    }

    private function spentToday(): float
    {
        return (float) $this->cache->remember(
            'docschat:spent:'.now()->toDateString(),
            self::CACHE_SECONDS,
            fn (): mixed => Message::query()
                ->where('role', MessageRole::Assistant)
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('cost_usd'),
        );
    }
}
