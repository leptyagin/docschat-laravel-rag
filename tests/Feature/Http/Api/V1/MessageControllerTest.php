<?php

declare(strict_types=1);

use App\Enums\MessageRole;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Services\Answer\ConversationTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\freezeTime;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeader;
use function Pest\Laravel\withServerVariables;

function chattingBot(string ...$answers): Bot
{
    fakeChat(...$answers);
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

it('stores a keyed hash instead of the IP address of a visitor', function (): void {
    $bot = chattingBot('Yes [1].');

    withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
    postMessage($bot, ['question' => 'Are dogs allowed?'])->streamedContent();

    $hash = Conversation::query()->firstOrFail()->visitor_hash;

    expect($hash)->toBe(hash_hmac('sha256', '203.0.113.7|Symfony', (string) config('app.key')))
        ->and(DB::table('conversations')->whereRaw('visitor_hash like ?', ['%203.0.113.7%'])->exists())->toBeFalse();
});

it('continues a conversation from its token, even from a new IP address', function (): void {
    $bot = chattingBot('Query', 'Yes [1].');
    $conversation = Conversation::factory()->for($bot)->create();
    Message::factory()->for($conversation)->create();

    withServerVariables(['REMOTE_ADDR' => '203.0.113.99']);
    postMessage($bot, ['question' => 'And cats?', 'conversation_token' => app(ConversationTokens::class)->issue($conversation)])
        ->assertOk()
        ->streamedContent();

    expect(Conversation::query()->count())->toBe(1)
        ->and($conversation->messages()->count())->toBe(3);
});

it('hands out a token that continues the same conversation', function (): void {
    $bot = chattingBot('Yes [1].', 'Query', 'Yes [1].');

    $first = serverSentEvents(postMessage($bot, ['question' => 'Hello']));

    postMessage($bot, ['question' => 'And cats?', 'conversation_token' => $first[0]['data']['conversation_token']])
        ->assertOk()
        ->streamedContent();

    expect(Conversation::query()->count())->toBe(1);
});

it('rejects a forged conversation token with 422', function (): void {
    $bot = chattingBot();
    $conversation = Conversation::factory()->for($bot)->create();

    postMessage($bot, ['question' => 'Hello', 'conversation_token' => "{$conversation->id}.forged"])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('conversation_token');
});

it('rejects a conversation of another bot with 422', function (): void {
    $bot = chattingBot();
    $conversation = Conversation::factory()->create();

    postMessage($bot, ['question' => 'Hello', 'conversation_token' => app(ConversationTokens::class)->issue($conversation)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('conversation_token');
});

it('limits a visitor to the configured messages per hour with a friendly 429', function (): void {
    config()->set('docschat.rate_limits.per_hour', 2);
    $bot = chattingBot();

    postMessage($bot, ['question' => 'One'])->assertOk()->streamedContent();
    postMessage($bot, ['question' => 'Two'])->assertOk()->streamedContent();

    postMessage($bot, ['question' => 'Three'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', "You've reached the message limit for this demo. Please come back a little later.");

    expect(Conversation::query()->count())->toBe(2);
});

it('tells a limited client when to come back', function (): void {
    freezeTime();
    config()->set('docschat.rate_limits.per_hour', 1);
    $bot = chattingBot();

    postMessage($bot, ['question' => 'One'])->assertOk()->streamedContent();

    postMessage($bot, ['question' => 'Two'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', 3600);
});

it('counts the limit per IP address, not per user agent', function (): void {
    config()->set('docschat.rate_limits.per_hour', 1);
    $bot = chattingBot();

    postMessage($bot, ['question' => 'One'])->assertOk()->streamedContent();

    withHeader('User-Agent', 'Another browser');

    postMessage($bot, ['question' => 'Two'])->assertTooManyRequests();
});

it('counts one limit for a whole IPv6 network', function (): void {
    config()->set('docschat.rate_limits.per_hour', 1);
    $bot = chattingBot();

    withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2::1']);
    postMessage($bot, ['question' => 'One'])->assertOk()->streamedContent();

    withServerVariables(['REMOTE_ADDR' => '2001:db8:1:ff00::2']);
    postMessage($bot, ['question' => 'Two'])->assertTooManyRequests();
});

it('does not let one visitor use up the limit of another', function (): void {
    config()->set('docschat.rate_limits.per_hour', 1);
    $bot = chattingBot();

    withServerVariables(['REMOTE_ADDR' => '203.0.113.1']);
    postMessage($bot, ['question' => 'One'])->assertOk()->streamedContent();

    withServerVariables(['REMOTE_ADDR' => '203.0.113.2']);
    postMessage($bot, ['question' => 'Two'])->assertOk()->streamedContent();
});

it('limits a visitor per day as well', function (): void {
    config()->set('docschat.rate_limits.per_day', 1);
    $bot = chattingBot();

    postMessage($bot, ['question' => 'One'])->assertOk()->streamedContent();

    postMessage($bot, ['question' => 'Two'])->assertTooManyRequests();
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
    'containing a NUL byte' => "a\u{0000}b",
]);

it('accepts a question at the maximum length', function (): void {
    postMessage(chattingBot('Yes [1].'), ['question' => str_repeat('a', 500)])->assertOk();
});

it('returns 404 for an unknown bot key', function (): void {
    postJson(route('api.v1.messages.store', 'pk_unknown'), ['question' => 'Hello'])->assertNotFound();
});
