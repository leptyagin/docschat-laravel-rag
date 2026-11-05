<?php

declare(strict_types=1);

use App\Enums\MessageRole;
use App\Enums\RefusalReason;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Admin\DashboardQuery;
use App\Services\Admin\UnansweredQuestions;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

/**
 * @param  array<string, mixed>  $answer
 */
function exchange(Conversation $conversation, string $question, array $answer = []): void
{
    Message::factory()->for($conversation)->create(['content' => $question, ...array_intersect_key($answer, ['created_at' => true])]);
    Message::factory()->for($conversation)->create([
        'role' => MessageRole::Assistant,
        'content' => 'Reply',
        'answered' => true,
        'cost_usd' => '0.001000',
        'latency_ms' => 1000,
        ...$answer,
    ]);
}

/**
 * @return array{answered: bool, refusal_reason: RefusalReason}
 */
function refused(RefusalReason $reason = RefusalReason::LowSimilarity): array
{
    return ['answered' => false, 'refusal_reason' => $reason];
}

it('counts questions for today and the last seven days', function (): void {
    $bot = managedBot();
    $conversation = Conversation::factory()->for($bot)->create();

    exchange($conversation, 'Today');
    exchange($conversation, 'Three days ago', ['created_at' => now()->subDays(3)]);
    exchange($conversation, 'Too old', ['created_at' => now()->subDays(8)]);
    exchange(Conversation::factory()->create(), 'Another bot');

    $stats = app(DashboardQuery::class)->stats($bot);

    expect($stats->questionsToday)->toBe(1)
        ->and($stats->questionsLastWeek)->toBe(2);
});

it('computes the answer rate, average cost and latency', function (): void {
    $bot = managedBot();
    $conversation = Conversation::factory()->for($bot)->create();

    exchange($conversation, 'One', ['cost_usd' => '0.002000', 'latency_ms' => 1000]);
    exchange($conversation, 'Two', ['cost_usd' => '0.004000', 'latency_ms' => 3000]);
    exchange($conversation, 'Three', [...refused(), 'cost_usd' => '0.000000', 'latency_ms' => 2000]);

    $stats = app(DashboardQuery::class)->stats($bot);

    expect($stats->answerRate)->toEqualWithDelta(2 / 3, 0.001)
        ->and($stats->averageCostUsd)->toEqualWithDelta(0.002, 0.000001)
        ->and($stats->averageLatencyMs)->toBe(2000);
});

it('leaves service failures out of the answer rate', function (): void {
    $bot = managedBot();
    $conversation = Conversation::factory()->for($bot)->create();

    exchange($conversation, 'One');
    exchange($conversation, 'Two', refused(RefusalReason::ModelError));
    exchange($conversation, 'Three', refused(RefusalReason::BudgetExceeded));

    expect(app(DashboardQuery::class)->stats($bot)->answerRate)->toBe(1.0);
});

it('shows dashes while there is no data yet', function (): void {
    actingAsAdmin();
    managedBot();

    get(route('admin.dashboard'))->assertOk()->assertSee('–')->assertSee('Nothing here yet');
});

it('groups unanswered questions by similar wording, most frequent first', function (): void {
    $bot = managedBot();
    $conversation = Conversation::factory()->for($bot)->create();

    exchange($conversation, 'Do you have a casino?', refused());
    exchange($conversation, 'Is there a casino at the hotel?', refused(RefusalReason::NoCitation));
    exchange($conversation, 'casino hotel', refused());
    exchange($conversation, 'What is the Wi-Fi password?', refused());

    $groups = app(UnansweredQuestions::class)->grouped($bot);

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->count)->toBe(3)
        ->and($groups[0]->variants)->toHaveCount(2)
        ->and($groups[1]->question)->toBe('What is the Wi-Fi password?');
});

it('counts refused and uncited answers, not outages or good answers', function (): void {
    $bot = managedBot();
    $conversation = Conversation::factory()->for($bot)->create();

    exchange($conversation, 'Answered fine');
    exchange($conversation, 'Provider was down', refused(RefusalReason::ModelError));
    exchange($conversation, 'Budget was spent', refused(RefusalReason::BudgetExceeded));

    expect(app(UnansweredQuestions::class)->grouped($bot))->toBe([]);
});

it('limits the groups on the dashboard', function (): void {
    $bot = managedBot();
    $conversation = Conversation::factory()->for($bot)->create();

    foreach (['alpha', 'bravo', 'charlie'] as $topic) {
        exchange($conversation, "Question about {$topic}", refused());
    }

    expect(app(UnansweredQuestions::class)->grouped($bot, 2))->toHaveCount(2);
});

it('does not run a query per question', function (): void {
    $bot = managedBot();
    $conversation = Conversation::factory()->for($bot)->create();

    foreach (range(1, 10) as $number) {
        exchange($conversation, "Topic number {$number} alone", refused());
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    app(UnansweredQuestions::class)->grouped($bot);

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('never shows questions of another bot', function (): void {
    actingAsAdmin();
    managedBot();
    exchange(Conversation::factory()->for(Bot::factory())->create(), 'A foreign question', refused());

    get(route('admin.unanswered'))->assertOk()->assertDontSee('A foreign question');
});
