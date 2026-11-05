<?php

declare(strict_types=1);

use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

it('lists conversations by their first question with totals', function (): void {
    actingAsAdmin();
    $conversation = Conversation::factory()->for(managedBot())->create();
    Message::factory()->for($conversation)->create(['content' => 'Where can I park?']);
    Message::factory()->for($conversation)->create(['role' => MessageRole::Assistant, 'cost_usd' => '0.001230']);

    get(route('admin.conversations.index'))
        ->assertOk()
        ->assertSee('Where can I park?')
        ->assertSee('2 messages')
        ->assertSee('$0.00123');
});

it('loads the list without a query per conversation', function (): void {
    actingAsAdmin();
    $bot = managedBot();
    Conversation::factory()->for($bot)->count(5)->has(Message::factory())->create();

    get(route('admin.conversations.index'))->assertOk();

    $few = count(DB::getQueryLog());

    DB::flushQueryLog();
    DB::enableQueryLog();
    Conversation::factory()->for($bot)->count(10)->has(Message::factory())->create();
    DB::flushQueryLog();

    get(route('admin.conversations.index'))->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(max($few, 4));
});

it('shows each answer with its stored sources, even after the chunks are gone', function (): void {
    actingAsAdmin();
    $conversation = Conversation::factory()->for(managedBot())->create();
    Message::factory()->for($conversation)->create(['content' => 'Pet fee?', 'rewritten_query' => 'Pet fee at Harbor View']);
    Message::factory()->for($conversation)->create([
        'role' => MessageRole::Assistant,
        'content' => 'It is 40 dollars [1].',
        'answered' => true,
        'model' => 'gpt-6-luna',
        'cited_chunk_ids' => [999],
        'sources' => [['number' => 1, 'title' => 'Pets Policy', 'page' => 2, 'url' => null, 'excerpt' => 'The pet fee is 40 dollars per stay.']],
    ]);

    get(route('admin.conversations.show', $conversation))
        ->assertOk()
        ->assertSee('Searched for: Pet fee at Harbor View')
        ->assertSee('[1] Pets Policy, page 2')
        ->assertSee('The pet fee is 40 dollars per stay.')
        ->assertSee('gpt-6-luna');
});

it('escapes what visitors typed', function (): void {
    actingAsAdmin();
    $conversation = Conversation::factory()->for(managedBot())->create();
    Message::factory()->for($conversation)->create(['content' => '<script>alert(1)</script>']);

    get(route('admin.conversations.show', $conversation))->assertDontSee('<script>alert(1)</script>', escape: false);
    get(route('admin.conversations.index'))->assertDontSee('<script>alert(1)</script>', escape: false);
});

it('returns 404 for a conversation of another bot', function (): void {
    actingAsAdmin();
    managedBot();

    get(route('admin.conversations.show', Conversation::factory()->create()))->assertNotFound();
});
