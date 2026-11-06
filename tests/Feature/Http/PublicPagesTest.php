<?php

declare(strict_types=1);

use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Document;

use function Pest\Laravel\get;

/**
 * The URL of each page an anonymous visitor can open.
 *
 * @return array<string, array{Closure(Bot, Document): string}>
 */
function publicPages(): array
{
    return [
        'landing page' => [fn (Bot $bot, Document $document): string => route('home')],
        'widget' => [fn (Bot $bot, Document $document): string => route('widget.show', $bot->public_key)],
        'reader' => [fn (Bot $bot, Document $document): string => route('reader.show', [$bot->public_key, $document])],
    ];
}

function readableDocument(Bot $bot): Document
{
    $document = Document::factory()->for($bot)->ready()->create();

    Chunk::factory()->published()->for($document)->for($bot)->create();

    return $document;
}

it('starts no session for an anonymous visitor', function (Closure $url): void {
    $bot = managedBot();

    $response = get($url($bot, readableDocument($bot)));

    $response->assertOk();

    expect($response->headers->getCookies())->toBe([]);
})->with(publicPages());

it('limits how fast one visitor can open pages', function (string $routeName): void {
    config()->set('docschat.rate_limits.pages_per_minute', 1);
    $url = route($routeName, $routeName === 'widget.show' ? managedBot()->public_key : []);

    get($url)->assertOk();
    get($url)->assertTooManyRequests();
})->with(['home', 'widget.show', 'admin.login']);

it('answers a malformed id with 404', function (Closure $url): void {
    $bot = Bot::factory()->create();

    get($url($bot))->assertNotFound();
})->with([
    'file, letters' => [fn (Bot $bot): string => "/widget/{$bot->public_key}/documents/abc"],
    'file, too long' => [fn (Bot $bot): string => "/widget/{$bot->public_key}/documents/99999999999999999999999"],
    'reader, letters' => [fn (Bot $bot): string => "/read/{$bot->public_key}/abc"],
]);

it('answers a malformed id in the panel with 404', function (string $url): void {
    actingAsAdmin();
    managedBot();

    get($url)->assertNotFound();
})->with(['/admin/conversations/abc', '/admin/conversations/99999999999999999999999']);

it('does not serve the storage disk over HTTP', function (): void {
    get('/storage/documents/1/guide.pdf')->assertNotFound();
});

describe('a bot that keeps its documents private', function (): void {
    it('opens no document in the reader', function (): void {
        $bot = Bot::factory()->create(['documents_public' => false]);

        get(route('reader.show', [$bot->public_key, readableDocument($bot)]))->assertNotFound();
    });

    it('lists no documents on the landing page', function (): void {
        $bot = Bot::factory()->create(['slug' => 'seabreeze', 'documents_public' => false]);
        $document = readableDocument($bot);

        get(route('home'))->assertOk()->assertDontSee($document->title);
    });
});
