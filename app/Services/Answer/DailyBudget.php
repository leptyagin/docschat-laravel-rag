<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Ai\Contracts\ChatModel;
use App\Data\Answer\BudgetReservation;
use App\Data\Answer\ModelUsage;
use App\Enums\MessageRole;
use App\Models\Message;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * A cap on what the public demo may spend per day across all bots. An answer
 * reserves its likely cost before calling a model and gives it back once the real
 * cost is stored, so answers running at the same moment count against the cap.
 *
 * The cap opens up through the day, so spending it all at once is not possible
 * and the bot recovers by itself during the same day.
 */
final readonly class DailyBudget
{
    private const int CACHE_SECONDS = 30;

    private const int RESERVATION_TTL_SECONDS = 172_800;

    private const int MICRODOLLARS_PER_DOLLAR = 1_000_000;

    private const int HOURS_PER_DAY = 24;

    /**
     * The system prompt, the question and the rewriting of a follow-up.
     */
    private const int PROMPT_OVERHEAD_TOKENS = 3_000;

    public function __construct(
        private Cache $cache,
        private CostCalculator $costs,
        private ChatModel $chat,
        #[Config('docschat.daily_budget_usd')] private float $limitUsd,
        #[Config('docschat.daily_budget_pace')] private int $pace,
        #[Config('docschat.retrieval.keep')] private int $contextChunks,
        #[Config('docschat.chunking.target_tokens')] private int $chunkTokens,
        #[Config('docschat.answers.max_answer_tokens')] private int $answerTokens,
    ) {}

    /**
     * @return BudgetReservation|null Null when the budget does not allow another answer now.
     */
    public function tryReserve(): ?BudgetReservation
    {
        $key = 'docschat:reserved:'.now()->toDateString();
        $microdollars = $this->likelyCostInMicrodollars();

        $this->cache->add($key, 0, self::RESERVATION_TTL_SECONDS);

        $reserved = (int) $this->cache->increment($key, $microdollars);

        if ($this->spentToday() + $reserved / self::MICRODOLLARS_PER_DOLLAR >= $this->allowanceSoFar()) {
            $this->cache->decrement($key, $microdollars);

            return null;
        }

        return new BudgetReservation($key, $microdollars);
    }

    /**
     * Call this after the real cost is stored, which the next check then reads.
     */
    public function release(BudgetReservation $reservation): void
    {
        $this->cache->decrement($reservation->key, $reservation->microdollars);
        $this->cache->forget($this->spentKey());
    }

    private function allowanceSoFar(): float
    {
        $shareOfDay = (now()->hour + 1) / self::HOURS_PER_DAY;

        return $this->limitUsd * min(1.0, $this->pace * $shareOfDay);
    }

    private function likelyCostInMicrodollars(): int
    {
        $cost = $this->costs->total([new ModelUsage(
            $this->chat->name(),
            $this->contextChunks * $this->chunkTokens + self::PROMPT_OVERHEAD_TOKENS,
            $this->answerTokens,
        )]);

        return (int) round((float) $cost * self::MICRODOLLARS_PER_DOLLAR);
    }

    private function spentToday(): float
    {
        return (float) $this->cache->remember(
            $this->spentKey(),
            self::CACHE_SECONDS,
            fn (): mixed => Message::query()
                ->where('role', MessageRole::Assistant)
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('cost_usd'),
        );
    }

    private function spentKey(): string
    {
        return 'docschat:spent:'.now()->toDateString();
    }
}
