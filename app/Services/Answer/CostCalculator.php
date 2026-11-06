<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Data\Answer\ModelUsage;
use BcMath\Number;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Log;

/**
 * Sums exact decimals and rounds once at the end: a single query embedding costs
 * less than the smallest stored unit, so rounding each step would lose it.
 */
final readonly class CostCalculator
{
    private const int TOKENS_PER_PRICE_UNIT = 1_000_000;

    private const int STORED_SCALE = 6;

    /**
     * @param  array<string, array{input: string, output: string}>  $prices
     */
    public function __construct(
        #[Config('docschat.prices')] private array $prices,
    ) {}

    /**
     * @param  list<ModelUsage>  $usages
     * @return numeric-string The cost in US dollars.
     */
    public function total(array $usages): string
    {
        $total = new Number('0');

        foreach ($usages as $usage) {
            $total += $this->costOf($usage);
        }

        return $total->round(self::STORED_SCALE)->value;
    }

    private function costOf(ModelUsage $usage): Number
    {
        $price = $this->prices[$usage->model] ?? null;

        if ($price === null) {
            Log::warning('No price is configured for a model, so its usage counts as free.', ['model' => $usage->model]);

            return new Number('0');
        }

        $tokenCost = new Number($price['input']) * $usage->promptTokens
            + new Number($price['output']) * $usage->completionTokens;

        return $tokenCost / self::TOKENS_PER_PRICE_UNIT;
    }
}
