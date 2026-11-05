<?php

declare(strict_types=1);

use App\Ai\Contracts\ChatModel;
use App\Ai\Fakes\FakeChatModel;
use App\Enums\MessageRole;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Services\Answer\VisitorHasher;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withServerVariables;

function chattingBot(string ...$answers): Bot
{
    app()->instance(ChatModel::class, new FakeChatModel()->respondWith(...$answers));
    fakeEmbeddings()->returnVector(array_pad([1.0], 1536, 0.0));

    $bot = Bot::factory()->create();

    Chunk::factory()
        ->for(Document::factory()->for($bot)->state(['title' => 'Pets Policy']))
        ->for($bot)
        ->create(['embedding' => array_pad([1.0], 1536, 0.0)]);

    return $bot;
}

/**
 * @param  array<string, mixed>  $payload
 * @return TestResponse<Response>
 */
function postMessage(Bot $bot, array $payload): TestResponse
{
    return postJson(route('api.v1.messages.store', $bot->public_key), $payload);
}

/**
 * @param  TestResponse<Response>  $response
 * @return list<array{event: string, data: array<string, mixed>}>
 */
function serverSentEvents(TestResponse $response): array
{
    preg_match_all('/^event: (\S+)\ndata: (.+)$/m', (string) $response->streamedContent(), $matches, PREG_SET_ORDER);

    return array_map(
        fn (array $match): array => ['event' => $match[1], 'data' => json_decode($match[2], true, flags: JSON_THROW_ON_ERROR)],
        $matches,
    );
}

function currentVisitorHash(): string
{
    return app(VisitorHasher::class)->hash('127.0.0.1', 'Symfony');
}

it('streams an answer as server-sent events', function (): void {
    $bot = chattingBot('Dogs are welcome [1].');

    $response = postMessage($bot, ['question' => 'Are dogs allowed?']);

    $response->assertOk();

    $events = serverSentEvents($response);
    $deltas = array_filter($events, fn (array $event): bool => $event['event'] === 'delta');

    expect($response->headers->get('Content-Type'))->toStartWith('text/event-stream')
        ->and($response->headers->get('Cache-Control'))->toContain('no-transform')
        ->and($events[0]['event'])->toBe('started')
        ->and(implode('', array_map(fn (array $event): string => $event['data']['text'], $deltas)))->toBe('Dogs are welcome [1].')
        ->and(count($deltas))->toBeGreaterThan(1)
        ->and(end($events)['event'])->toBe('completed')
        ->and(end($events)['data']['answered'])->toBeTrue()
        ->and(end($events)['data']['sources'][0]['title'])->toBe('Pets Policy');
});

it('starts a conversation and stores both messages', function (): void {
    $bot = chattingBot('Yes [1].');

    postMessage($bot, ['question' => 'Are dogs allowed?'])->streamedContent();

    $conversation = Conversation::query()->firstOrFail();

    expect($conversation->bot_id)->toBe($bot->id)
        ->and($conversation->messages()->orderBy('id')->pluck('role')->all())->toBe([MessageRole::User, MessageRole::Assistant]);
});

it('never stores the raw IP address of a visitor', function (): void {
    $bot = chattingBot('Yes [1].');

    withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
    postMessage($bot, ['question' => 'Are dogs allowed?'])->streamedContent();

    $conversation = Conversation::query()->firstOrFail();

    expect($conversation->visitor_hash)->toHaveLength(64)
        ->and($conversation->visitor_hash)->not->toBe(hash('sha256', '203.0.113.7|Symfony'))
        ->and(json_encode($conversation->toArray()))->not->toContain('203.0.113.7');
});

it('continues the conversation of the same visitor', function (): void {
    $bot = chattingBot('Query', 'Yes [1].');
    $conversation = Conversation::factory()->for($bot)->create(['visitor_hash' => currentVisitorHash()]);
    Message::factory()->for($conversation)->create();

    postMessage($bot, ['question' => 'And cats?', 'conversation_id' => $conversation->id])->assertOk()->streamedContent();

    expect(Conversation::query()->count())->toBe(1)
        ->and($conversation->messages()->count())->toBe(3);
});

it('rejects a conversation of another visitor with 422', function (): void {
    $bot = chattingBot();
    $conversation = Conversation::factory()->for($bot)->create();

    postMessage($bot, ['question' => 'Hello', 'conversation_id' => $conversation->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('conversation_id');
});

it('rejects a conversation of another bot with 422', function (): void {
    $bot = chattingBot();
    $conversation = Conversation::factory()->create(['visitor_hash' => currentVisitorHash()]);

    postMessage($bot, ['question' => 'Hello', 'conversation_id' => $conversation->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('conversation_id');
});

it('rejects an invalid question with 422', function (mixed $question): void {
    postMessage(chattingBot(), ['question' => $question])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('question');

    expect(Message::query()->count())->toBe(0);
})->with([
    'missing' => null,
    'blank' => '   ',
    'not text' => [['nested']],
    'one character too long' => str_repeat('a', 501),
]);

it('accepts a question at the maximum length', function (): void {
    postMessage(chattingBot('Yes [1].'), ['question' => str_repeat('a', 500)])->assertOk();
});

it('returns 404 for an unknown bot key', function (): void {
    postJson(route('api.v1.messages.store', 'pk_unknown'), ['question' => 'Hello'])->assertNotFound();
});
