<?php

declare(strict_types=1);

use App\Data\Answer\ModelUsage;
use App\Services\Answer\CostCalculator;

function calculator(): CostCalculator
{
    return new CostCalculator([
        'chat-model' => ['input' => '0.10', 'output' => '0.50'],
        'embedding-model' => ['input' => '0.02', 'output' => '0'],
    ], ['input' => '5.00', 'output' => '25.00']);
}

it('prices input and output tokens per million', function (): void {
    expect(calculator()->total([new ModelUsage('chat-model', 3600, 400)]))->toBe('0.000560');
});

it('adds up all the models used for an answer', function (): void {
    expect(calculator()->total([
        new ModelUsage('chat-model', 1000, 100),
        new ModelUsage('embedding-model', 50_000),
    ]))->toBe('0.001150');
});

it('keeps amounts smaller than the stored unit until the final rounding', function (): void {
    $tinyUsage = new ModelUsage('embedding-model', 10);

    expect(calculator()->total(array_fill(0, 100, $tinyUsage)))->toBe('0.000020');
});

it('returns zero when nothing was used', function (): void {
    expect(calculator()->total([]))->toBe('0.000000');
});
