<?php

declare(strict_types=1);

use App\Enums\MessageRole;
use App\Models\Message;
use App\Services\Answer\DailyBudget;

use function Pest\Laravel\travelTo;

/**
 * A price that makes one likely answer cost about a dollar.
 */
function dollarAnswers(): void
{
    config()->set('docschat.prices.fake-chat', ['input' => '0', 'output' => '2500']);
    config()->set('docschat.answers.max_answer_tokens', 400);
}

function spend(string $usd): void
{
    Message::factory()->create(['role' => MessageRole::Assistant, 'cost_usd' => $usd]);
}

beforeEach(function (): void {
    fakeChat();
    travelTo(now()->setTime(23, 0));
});

it('counts answers that are still running', function (): void {
    dollarAnswers();
    config()->set('docschat.daily_budget_usd', 2.5);

    $budget = app(DailyBudget::class);

    expect($budget->tryReserve())->not->toBeNull()
        ->and($budget->tryReserve())->not->toBeNull()
        ->and($budget->tryReserve())->toBeNull();
});

it('frees the reservation of a finished answer', function (): void {
    dollarAnswers();
    config()->set('docschat.daily_budget_usd', 1.5);

    $budget = app(DailyBudget::class);
    $reservation = $budget->tryReserve();

    expect($budget->tryReserve())->toBeNull();

    $budget->release($reservation);

    expect($budget->tryReserve())->not->toBeNull();
});

it('sees the cost of a finished answer at once, without waiting for the cache', function (): void {
    config()->set('docschat.daily_budget_usd', 1.0);

    $budget = app(DailyBudget::class);
    $reservation = $budget->tryReserve();

    spend('1.000000');
    $budget->release($reservation);

    expect($budget->tryReserve())->toBeNull();
});

it('releases the budget through the day, so a burst cannot spend it all', function (): void {
    config()->set('docschat.daily_budget_usd', 24.0);
    config()->set('docschat.daily_budget_pace', 4);

    travelTo(now()->setTime(0, 30));
    spend('4.000000');

    expect(app(DailyBudget::class)->tryReserve())->toBeNull();

    travelTo(now()->setTime(1, 30));
    cache()->flush();

    expect(app(DailyBudget::class)->tryReserve())->not->toBeNull();
});

it('allows the whole budget in the end, and no more', function (): void {
    config()->set('docschat.daily_budget_usd', 24.0);
    config()->set('docschat.daily_budget_pace', 4);

    spend('23.000000');

    expect(app(DailyBudget::class)->tryReserve())->not->toBeNull();

    spend('1.000000');
    cache()->flush();

    expect(app(DailyBudget::class)->tryReserve())->toBeNull();
});
