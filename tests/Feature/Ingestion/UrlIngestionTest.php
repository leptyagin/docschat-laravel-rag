<?php

declare(strict_types=1);

use App\Actions\Ingestion\IngestUrl;
use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Document;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const PAGE_URL = 'https://hotel.example/parking';

const PAGE_HTML = '<html><head><title>Parking guide</title></head><body><main><h1>Parking</h1><p>The garage is open all night.</p></main></body></html>';

/**
 * A sync queue rethrows what the job has already recorded on the document.
 */
function ingestPage(string $url = PAGE_URL): Document
{
    rescue(fn (): Document => app(IngestUrl::class)->handle(Bot::factory()->create(), $url), report: false);

    return Document::query()->latest('id')->firstOrFail();
}

function htmlResponse(string $body = PAGE_HTML): PromiseInterface
{
    return Http::response($body, 200, ['Content-Type' => 'text/html; charset=utf-8']);
}

it('indexes the main content of a web page', function (): void {
    Http::preventStrayRequests();
    fakeDns();
    fakeEmbeddings();
    Http::fake([
        'hotel.example/robots.txt' => Http::response('', 404),
        PAGE_URL => htmlResponse(),
    ]);

    $document = ingestPage();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->source_type)->toBe(DocumentSourceType::Url)
        ->and($document->source_url)->toBe(PAGE_URL)
        ->and($document->title)->toBe('Parking guide')
        ->and(Chunk::query()->firstOrFail()->content)->toBe('The garage is open all night.')
        ->and(Chunk::query()->firstOrFail()->page)->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent', 'DocsChatBot/1.0'));
});

it('does not re-embed a page whose text is unchanged', function (): void {
    Http::preventStrayRequests();
    fakeDns();
    $embeddings = fakeEmbeddings();
    Http::fake([
        'hotel.example/robots.txt' => Http::response('', 404),
        PAGE_URL => Http::sequence()
            ->push(PAGE_HTML, 200, ['Content-Type' => 'text/html'])
            ->push(str_replace('<body>', '<body class="changed-markup">', PAGE_HTML), 200, ['Content-Type' => 'text/html']),
    ]);
    $bot = Bot::factory()->create();

    app(IngestUrl::class)->handle($bot, PAGE_URL);
    $document = app(IngestUrl::class)->handle($bot, PAGE_URL);

    expect($embeddings->batches)->toHaveCount(1)
        ->and($document->refresh()->status)->toBe(DocumentStatus::Ready);
});

it('respects a robots.txt that disallows the page', function (): void {
    Http::preventStrayRequests();
    fakeDns();
    fakeEmbeddings();
    Http::fake(['hotel.example/robots.txt' => Http::response("User-agent: *\nDisallow: /parking")]);

    $document = ingestPage();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error)->toContain('robots.txt');

    Http::assertNotSent(fn (Request $request): bool => $request->url() === PAGE_URL);
});

it('respects a robots.txt rule aimed at this crawler', function (): void {
    Http::preventStrayRequests();
    fakeDns();
    fakeEmbeddings();
    Http::fake(['hotel.example/robots.txt' => Http::response("User-agent: DocsChatBot\nDisallow: /\n")]);

    expect(ingestPage()->status)->toBe(DocumentStatus::Failed);
});

it('crawls a page that robots.txt leaves allowed', function (): void {
    Http::preventStrayRequests();
    fakeDns();
    fakeEmbeddings();
    Http::fake([
        'hotel.example/robots.txt' => Http::response("User-agent: *\nDisallow: /admin"),
        PAGE_URL => htmlResponse(),
    ]);

    expect(ingestPage()->status)->toBe(DocumentStatus::Ready);
});

it('does not fetch the page while robots.txt returns a server error', function (): void {
    Http::preventStrayRequests();
    fakeDns();
    fakeEmbeddings();
    Http::fake(['hotel.example/robots.txt' => Http::response('', 503)]);

    expect(ingestPage()->status)->toBe(DocumentStatus::Failed);

    Http::assertNotSent(fn (Request $request): bool => $request->url() === PAGE_URL);
});

it('records why a page could not be used', function (string $body, int $status, string $contentType, string $error): void {
    Http::preventStrayRequests();
    fakeDns();
    fakeEmbeddings();
    Http::fake([
        'hotel.example/robots.txt' => Http::response('', 404),
        PAGE_URL => Http::response($body, $status, ['Content-Type' => $contentType]),
    ]);

    $document = ingestPage();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error)->toContain($error);
})->with([
    'missing page' => ['Not found', 404, 'text/html', 'HTTP 404'],
    'not a web page' => ['%PDF-1.4', 200, 'application/pdf', 'does not point to a web page'],
    'page without text' => ['<html><body><script>1</script></body></html>', 200, 'text/html', 'No text could be extracted'],
]);

it('refuses a page larger than the configured limit', function (): void {
    Http::preventStrayRequests();
    fakeDns();
    fakeEmbeddings();
    config()->set('docschat.ingestion.http.max_bytes', 100);
    Http::fake([
        'hotel.example/robots.txt' => Http::response('', 404),
        PAGE_URL => htmlResponse(str_repeat('x', 101)),
    ]);

    expect(ingestPage()->error)->toContain('too large');
});

it('follows a redirect to a public page', function (): void {
    Http::preventStrayRequests();
    fakeDns();
    fakeEmbeddings();
    Http::fake([
        'hotel.example/robots.txt' => Http::response('', 404),
        PAGE_URL => Http::response('', 301, ['Location' => '/parking-guide']),
        'hotel.example/parking-guide' => htmlResponse(),
    ]);

    expect(ingestPage()->status)->toBe(DocumentStatus::Ready);
});

it('gives up on a redirect loop', function (): void {
    Http::preventStrayRequests();
    fakeDns();
    fakeEmbeddings();
    Http::fake([
        'hotel.example/robots.txt' => Http::response('', 404),
        PAGE_URL => Http::response('', 302, ['Location' => PAGE_URL]),
    ]);

    expect(ingestPage()->error)->toContain('too many times');
});

describe('server-side request forgery', function (): void {
    it('refuses a host that resolves to a private address', function (string $address): void {
        Http::preventStrayRequests();
        fakeDns([$address]);
        fakeEmbeddings();

        expect(ingestPage()->error)->toContain('Only public');

        Http::assertNothingSent();
    })->with([
        'loopback' => '127.0.0.1',
        'private network' => '10.0.0.5',
        'cloud metadata' => '169.254.169.254',
        'ipv6 loopback' => '::1',
        'ipv6 unique local' => 'fd00::1',
    ]);

    it('refuses a host when any of its addresses is private', function (): void {
        Http::preventStrayRequests();
        fakeDns(['93.184.216.34', '10.0.0.5']);
        fakeEmbeddings();

        expect(ingestPage()->status)->toBe(DocumentStatus::Failed);

        Http::assertNothingSent();
    });

    it('refuses a redirect into the private network', function (): void {
        Http::preventStrayRequests();
        fakeEmbeddings();
        Http::fake([
            'hotel.example/robots.txt' => Http::response('', 404),
            PAGE_URL => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
        ]);
        fakeDns();

        expect(ingestPage()->error)->toContain('Only public');

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '169.254'));
    });

    it('rejects a URL that cannot be public before queueing anything', function (string $url): void {
        app(IngestUrl::class)->handle(Bot::factory()->create(), $url);
    })->throws(UnprocessableSourceException::class)->with([
        'file scheme' => 'file:///etc/passwd',
        'ftp scheme' => 'ftp://hotel.example/file',
        'embedded credentials' => 'https://user:secret@hotel.example/',
        'custom port' => 'https://hotel.example:6379/',
        'not a URL' => 'not a url',
    ]);

    it('refuses addresses written directly in the URL', function (string $url): void {
        Http::preventStrayRequests();
        fakeDns();
        fakeEmbeddings();

        expect(ingestPage($url)->error)->toContain('Only public');

        Http::assertNothingSent();
    })->with([
        'ipv4 literal' => 'http://127.0.0.1/',
        'decimal ipv4' => 'http://2130706433/',
        'hex ipv4' => 'http://0x7f.0.0.1/',
        'ipv6 literal' => 'http://[::1]/',
    ]);
});
