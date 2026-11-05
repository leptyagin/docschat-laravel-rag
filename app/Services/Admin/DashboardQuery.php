<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Data\Admin\DashboardStats;
use App\Enums\MessageRole;
use App\Enums\RefusalReason;
use App\Models\Bot;
use App\Models\Message;

/**
 * Service failures are left out of the answer rate: it measures how well the
 * documents cover what visitors ask, not whether a provider was reachable.
 */
final class DashboardQuery
{
    public function stats(Bot $bot): DashboardStats
    {
        $weekStart = now()->subDays(7);
        $conversationIds = $bot->conversations()->select('id');

        /** @var object{today: int, week: int} $questions */
        $questions = Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('role', MessageRole::User)
            ->where('created_at', '>=', $weekStart)
            ->toBase()
            ->selectRaw('count(*) filter (where created_at >= ?) as today, count(*) as week', [now()->startOfDay()])
            ->first();

        /** @var object{total: int, answered: int, cost: string|null, latency: string|null} $answers */
        $answers = Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('role', MessageRole::Assistant)
            ->where('created_at', '>=', $weekStart)
            ->where(fn ($query) => $query
                ->whereNull('refusal_reason')
                ->orWhereIn('refusal_reason', [RefusalReason::LowSimilarity, RefusalReason::NoCitation]))
            ->toBase()
            ->selectRaw('count(*) as total, count(*) filter (where answered) as answered, avg(cost_usd) as cost, avg(latency_ms) as latency')
            ->first();

        return new DashboardStats(
            questionsToday: (int) $questions->today,
            questionsLastWeek: (int) $questions->week,
            answerRate: $answers->total > 0 ? $answers->answered / $answers->total : null,
            averageCostUsd: $answers->cost === null ? null : (float) $answers->cost,
            averageLatencyMs: $answers->latency === null ? null : (int) round((float) $answers->latency),
        );
    }
}
