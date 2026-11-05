<?php

declare(strict_types=1);

namespace App\Data\Admin;

final readonly class DashboardStats
{
    public function __construct(
        public int $questionsToday,
        public int $questionsLastWeek,
        public ?float $answerRate,
        public ?float $averageCostUsd,
        public ?int $averageLatencyMs,
    ) {}
}
