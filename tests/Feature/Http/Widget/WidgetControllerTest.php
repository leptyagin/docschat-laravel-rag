<?php

declare(strict_types=1);

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Models\Bot;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Laravel\withHeader;

it('renders the widget page with the bot configuration', function (): void {
    $bot = Bot::factory()->create(['name' => 'Marina Bot', 'greeting' => 'Ahoy!']);

    get(route('widget.show', $bot->public_key))
        ->assertOk()
        ->assertSee('Marina Bot')
        ->assertSee('Ahoy!')
        ->assertSee('widget.js');
});

it('can be framed by any site, but runs only its own script', function (): void {
    $response = get(route('widget.show', Bot::factory()->create()->public_key));

    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($policy)->toContain('frame-ancestors *')
        ->and($policy)->toContain("script-src 'self'")
        ->and($response->headers->get('X-Frame-Options'))->toBeNull();
});

it('warns visitors of the public demo bot that their questions are visible', function (): void {
    get(route('widget.show', managedBot()->public_key))->assertSee('appear in the public read-only admin panel');
});

it('shows no demo warning for any other bot', function (): void {
    get(route('widget.show', Bot::factory()->create()->public_key))->assertDontSee('appear in the public read-only admin panel');
});

it('takes the color and greeting from the embedding page', function (): void {
    $bot = Bot::factory()->create();

    withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('widget.show', [$bot->public_key, 'color' => '#ff0000', 'greeting' => 'Hello from the site']))
        ->assertOk()
        ->assertSee('#ff0000')
        ->assertSee('Hello from the site');
});

it('ignores a greeting in a link opened directly, so no link can speak for the bot', function (): void {
    $bot = Bot::factory()->create(['greeting' => 'Ahoy!']);

    get(route('widget.show', [$bot->public_key, 'greeting' => 'Send your card number to evil.example']))
        ->assertOk()
        ->assertSee('Ahoy!')
        ->assertDontSee('evil.example');
});

it('rejects a greeting that is not valid UTF-8', function (): void {
    withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('widget.show', Bot::factory()->create()->public_key).'?greeting=%FF%FE')
        ->assertUnprocessable();
});

it('can be framed only by the allowed sites once the bot names any', function (): void {
    $bot = Bot::factory()->create(['embed_origins' => ['https://hotel.example', 'https://www.hotel.example']]);

    $policy = (string) get(route('widget.show', $bot->public_key))->headers->get('Content-Security-Policy');

    expect($policy)->toContain('frame-ancestors https://hotel.example https://www.hotel.example')
        ->and($policy)->not->toContain('frame-ancestors *');
});

it('cannot be broken out of through the greeting', function (): void {
    $bot = Bot::factory()->create();

    withHeader('Sec-Fetch-Dest', 'iframe')
        ->get(route('widget.show', [$bot->public_key, 'greeting' => '</script><script>alert(1)</script>']))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

it('rejects a color that is not a color', function (): void {
    get(route('widget.show', [Bot::factory()->create()->public_key, 'color' => 'red;background:url(x)']))
        ->assertUnprocessable();
});

it('returns 404 for an unknown bot', function (): void {
    get(route('widget.show', 'pk_unknown'))->assertNotFound();
});

describe('source files', function (): void {
    it('serves a PDF inline, sandboxed', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('documents/guide.pdf', '%PDF-1.4');
        $bot = Bot::factory()->create();
        $document = Document::factory()->for($bot)->ready()->create(['source_type' => DocumentSourceType::Pdf, 'file_path' => 'documents/guide.pdf']);

        $response = get(route('widget.documents.show', [$bot->public_key, $document]));

        $response->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('application/pdf')
            ->and($response->headers->get('Content-Security-Policy'))->toBe('sandbox')
            ->and($response->headers->get('Content-Disposition'))->toStartWith('inline');
    });

    it('serves a text file as plain text, so markup in it cannot run', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('documents/guide.md', '<script>alert(1)</script>');
        $bot = Bot::factory()->create();
        $document = Document::factory()->for($bot)->ready()->create(['file_path' => 'documents/guide.md']);

        $response = get(route('widget.documents.show', [$bot->public_key, $document]));

        expect($response->headers->get('Content-Type'))->toStartWith('text/plain');
    });

    it('does not serve a file that was never indexed', function (DocumentStatus $status): void {
        Storage::fake('local');
        Storage::disk('local')->put('documents/draft.md', 'Not published');
        $bot = Bot::factory()->create();
        $document = Document::factory()->for($bot)->create(['file_path' => 'documents/draft.md', 'status' => $status, 'chunk_count' => 0]);

        get(route('widget.documents.show', [$bot->public_key, $document]))->assertNotFound();
    })->with([DocumentStatus::Pending, DocumentStatus::Failed]);

    it('does not serve files of a bot that keeps its documents private', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('documents/guide.md', 'Internal');
        $bot = Bot::factory()->create(['documents_public' => false]);
        $document = Document::factory()->for($bot)->ready()->create(['file_path' => 'documents/guide.md']);

        get(route('widget.documents.show', [$bot->public_key, $document]))->assertNotFound();
    });

    it('limits how fast one visitor can pull files with 429', function (): void {
        config()->set('docschat.rate_limits.documents_per_minute', 1);
        Storage::fake('local');
        Storage::disk('local')->put('documents/guide.md', 'Text');
        $bot = Bot::factory()->create();
        $url = route('widget.documents.show', [$bot->public_key, Document::factory()->for($bot)->ready()->create(['file_path' => 'documents/guide.md'])]);

        get($url)->assertOk();
        get($url)->assertTooManyRequests();
    });

    it('returns 404 for a document of another bot', function (): void {
        Storage::fake('local');
        $document = Document::factory()->create();

        get(route('widget.documents.show', [Bot::factory()->create()->public_key, $document]))->assertNotFound();
    });

    it('returns 404 for a web page document, which has no file', function (): void {
        $bot = Bot::factory()->create();
        $document = Document::factory()->for($bot)->fromUrl('https://hotel.example')->create();

        get(route('widget.documents.show', [$bot->public_key, $document]))->assertNotFound();
    });
});
