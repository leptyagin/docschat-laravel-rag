<?php

declare(strict_types=1);

use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Conversation;
use App\Models\Document;
use Illuminate\Http\Middleware\TrustProxies;

use function Pest\Laravel\call;
use function Pest\Laravel\withHeader;
use function Pest\Laravel\withHeaders;
use function Pest\Laravel\withServerVariables;

function botThatCanAnswer(): Bot
{
    fakeChat('Yes [1].');
    fakeEmbeddings()->returnVector(array_pad([1.0], 1536, 0.0));

    $bot = Bot::factory()->create();

    Chunk::factory()
        ->for(Document::factory()->for($bot))
        ->for($bot)
        ->create(['embedding' => array_pad([1.0], 1536, 0.0)]);

    return $bot;
}

it('refuses a request from another site before anything happens', function (string $origin): void {
    $bot = botThatCanAnswer();

    withHeader('Origin', $origin)
        ->post(route('api.v1.messages.store', $bot->public_key), ['question' => 'Are dogs allowed?'], ['Accept' => 'application/json'])
        ->assertForbidden();

    expect(Conversation::query()->count())->toBe(0);
})->with(['https://evil.example', 'null', 'http://localhost.evil.example']);

it('does not let another site use up the limit of a visitor', function (): void {
    config()->set('docschat.rate_limits.per_hour', 1);
    $bot = botThatCanAnswer();
    $url = route('api.v1.messages.store', $bot->public_key);

    withHeader('Origin', 'https://evil.example')->post($url, ['question' => 'Are dogs allowed?'])->assertForbidden();

    withHeader('Origin', url('/'))->post($url, ['question' => 'Are dogs allowed?'])->assertOk();
});

it('accepts the chat itself, and clients that are not browsers', function (bool $sendsOrigin): void {
    $bot = botThatCanAnswer();
    $url = route('api.v1.messages.store', $bot->public_key);

    ($sendsOrigin ? withHeader('Origin', url('/')) : test())
        ->post($url, ['question' => 'Are dogs allowed?'])
        ->assertOk();
})->with(['from the chat' => [true], 'without an origin' => [false]]);

it('guards feedback too', function (): void {
    $bot = Bot::factory()->create();

    withHeader('Origin', 'https://evil.example')
        ->post(route('api.v1.feedback.store', [$bot->public_key, 1]), ['feedback' => 'up'])
        ->assertForbidden();
});

it('tells browsers that no other site may read the API', function (): void {
    $bot = Bot::factory()->create();

    $response = call('OPTIONS', route('api.v1.messages.store', $bot->public_key), server: [
        'HTTP_ORIGIN' => 'https://evil.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});

it('recognises the chat behind a proxy that terminates HTTPS', function (): void {
    TrustProxies::at('10.0.0.0/8');
    $bot = botThatCanAnswer();

    withServerVariables(['REMOTE_ADDR' => '10.0.0.5']);

    withHeaders([
        'Origin' => 'https://docs.example',
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'docs.example',
        'X-Forwarded-Port' => '443',
    ])->post(route('api.v1.messages.store', $bot->public_key), ['question' => 'Are dogs allowed?'])->assertOk();

    TrustProxies::flushState();
});
