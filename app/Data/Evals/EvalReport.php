<?php

declare(strict_types=1);

namespace App\Data\Evals;

use BcMath\Number;

final readonly class EvalReport
{
    /**
     * @param  list<EvalResult>  $results
     */
    public function __construct(public array $results) {}

    /**
     * @return list<EvalResult>
     */
    public function answerable(): array
    {
        return array_values(array_filter($this->results, fn (EvalResult $result): bool => ! $result->question->shouldRefuse));
    }

    /**
     * @return list<EvalResult>
     */
    public function unanswerable(): array
    {
        return array_values(array_filter($this->results, fn (EvalResult $result): bool => $result->question->shouldRefuse));
    }

    /**
     * @return list<EvalResult>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->results, fn (EvalResult $result): bool => ! $result->passed()));
    }

    public function sourceHits(): int
    {
        return count(array_filter($this->answerable(), fn (EvalResult $result): bool => $result->hitExpectedSource()));
    }

    public function keywordHits(): int
    {
        return count(array_filter(
            $this->answerable(),
            fn (EvalResult $result): bool => $result->answered && $result->containsExpectedWords(),
        ));
    }

    public function correctRefusals(): int
    {
        return count(array_filter($this->unanswerable(), fn (EvalResult $result): bool => ! $result->answered));
    }

    public function falseRefusals(): int
    {
        return count(array_filter($this->answerable(), fn (EvalResult $result): bool => ! $result->answered));
    }

    /**
     * @return numeric-string
     */
    public function totalCost(): string
    {
        $total = new Number('0');

        foreach ($this->results as $result) {
            $total += new Number($result->costUsd);
        }

        return $total->round(6)->value;
    }

    /**
     * @return numeric-string
     */
    public function averageCost(): string
    {
        return $this->results === []
            ? '0.000000'
            : (new Number($this->totalCost()) / count($this->results))->round(6)->value;
    }

    public function averageLatencyMs(): int
    {
        return $this->results === []
            ? 0
            : (int) round(array_sum(array_map(fn (EvalResult $result): int => $result->latencyMs, $this->results)) / count($this->results));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => [
                'questions' => count($this->results),
                'answerable' => count($this->answerable()),
                'unanswerable' => count($this->unanswerable()),
                'source_hits' => $this->sourceHits(),
                'keyword_hits' => $this->keywordHits(),
                'correct_refusals' => $this->correctRefusals(),
                'false_refusals' => $this->falseRefusals(),
                'total_cost_usd' => $this->totalCost(),
                'average_cost_usd' => $this->averageCost(),
                'average_latency_ms' => $this->averageLatencyMs(),
            ],
            'results' => array_map(fn (EvalResult $result): array => $result->toArray(), $this->results),
        ];
    }
}
