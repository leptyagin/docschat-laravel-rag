<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    config()->set('docschat.conversation_retention_days', 30);
});

function conversationStartedDaysAgo(int $days, ?int $lastMessageDaysAgo = null): Conversation
{
    $conversation = Conversation::factory()->create(['created_at' => now()->subDays($days)]);

    Message::factory()->for($conversation)->create(['created_at' => now()->subDays($lastMessageDaysAgo ?? $days)]);

    return $conversation;
}

it('deletes old conversations with their messages', function (): void {
    conversationStartedDaysAgo(31);

    artisan('model:prune', ['--model' => [Conversation::class]])->assertSuccessful();

    expect(Conversation::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0);
});

it('keeps recent conversations, and old ones that are still in use', function (): void {
    $recent = conversationStartedDaysAgo(29);
    $stillInUse = conversationStartedDaysAgo(90, lastMessageDaysAgo: 1);

    artisan('model:prune', ['--model' => [Conversation::class]])->assertSuccessful();

    expect(Conversation::query()->pluck('id')->sort()->values()->all())->toBe([$recent->id, $stillInUse->id]);
});

it('runs every day', function (): void {
    $commands = collect(app(Schedule::class)->events())->map(fn (Event $event): string => (string) $event->command);

    expect($commands->contains(fn (string $command): bool => str_contains($command, 'model:prune')))->toBeTrue();
});
