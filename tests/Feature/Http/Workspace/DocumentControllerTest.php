<?php

declare(strict_types=1);

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

/**
 * @param  list<array{0: string|null, 1: string, 2?: int|null}>  $passages  [heading, content, page]
 * @param  array<string, mixed>  $attributes
 */
function documentWith(Bot $bot, array $passages, array $attributes = []): Document
{
    $document = Document::factory()->for($bot)->ready()->create(['title' => 'Pets Policy', ...$attributes]);

    foreach ($passages as $position => $passage) {
        Chunk::factory()->published()->for($document)->for($bot)->create([
            'position' => $position,
            'heading' => $passage[0],
            'content' => $passage[1],
            'page' => $passage[2] ?? null,
        ]);
    }

    return $document;
}

function readerUrl(Bot $bot, Document|int $document): string
{
    return route('reader.show', [$bot->public_key, $document]);
}

it('shows a document with the assistant of its bot beside it', function (): void {
    $bot = Bot::factory()->create(['name' => 'Marina Bot', 'greeting' => 'Ahoy!']);
    $document = documentWith($bot, [['Pets Policy', 'Dogs are welcome.']]);

    get(readerUrl($bot, $document))
        ->assertOk()
        ->assertSee('Pets Policy')
        ->assertSee('Dogs are welcome.')
        ->assertSee('Marina Bot')
        ->assertSee('Ahoy!')
        ->assertSee('data-bot="'.$bot->public_key.'"', escape: false)
        ->assertSee('name="robots" content="noindex"', escape: false)
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('rebuilds the headings of the document from the trails of its chunks', function (): void {
    $bot = Bot::factory()->create();
    $document = documentWith($bot, [
        ['Pets Policy', 'Opening text.'],
        ['Pets Policy > Fees > Harbor View', 'Forty dollars.'],
        ['Pets Policy > Fees > Old Town', 'No pets.'],
    ]);

    get(readerUrl($bot, $document))
        ->assertOk()
        ->assertSeeInOrder(['Opening text.', '<h2 id="fees">Fees</h2>', '<h3 id="harbor-view">Harbor View</h3>', 'Forty dollars.', '<h3 id="old-town">Old Town</h3>'], escape: false);
});

it('gives repeated headings their own anchors', function (): void {
    $bot = Bot::factory()->create();
    $document = documentWith($bot, [
        ['Guide > Parking > Old Town', 'A.'],
        ['Guide > Transfers > Old Town', 'B.'],
    ]);

    get(readerUrl($bot, $document))
        ->assertSee('id="old-town"', escape: false)
        ->assertSee('id="old-town-2"', escape: false);
});

it('keeps the anchor of every passage, which answers link to', function (): void {
    $bot = Bot::factory()->create();
    $document = documentWith($bot, [['A', 'First.'], ['A > B', 'Second.']]);

    get(readerUrl($bot, $document))
        ->assertSee('id="passage-0"', escape: false)
        ->assertSee('id="passage-1"', escape: false);
});

it('shows the text repeated between two chunks of a long section only once', function (): void {
    $bot = Bot::factory()->create();
    $document = documentWith($bot, [
        ['Rules', "Pets are welcome.\nThe tail repeats here."],
        ['Rules', "The tail repeats here.\n\nA new paragraph follows."],
    ]);

    $html = get(readerUrl($bot, $document))->assertSee('A new paragraph follows.')->getContent();

    expect(substr_count((string) $html, 'The tail repeats here.'))->toBe(1);
});

it('keeps a repeated paragraph that is not an overlap', function (): void {
    $bot = Bot::factory()->create();
    $document = documentWith($bot, [
        ['Rules', 'First paragraph.'],
        ['Rules', "Different opening.\n\nFirst paragraph."],
    ]);

    get(readerUrl($bot, $document))->assertSee('Different opening.');
});

it('marks where each page of a PDF starts', function (): void {
    $bot = Bot::factory()->create();
    $document = documentWith($bot, [['A', 'One.', 1], ['A > B', 'Two.', 1], ['A > C', 'Three.', 2]], ['source_type' => DocumentSourceType::Pdf]);

    $html = (string) get(readerUrl($bot, $document))->assertSee('Page 2')->getContent();

    expect(substr_count($html, 'Page 1'))->toBe(1);
});

it('renders tables and lists from the document', function (): void {
    $bot = Bot::factory()->create();
    $document = documentWith($bot, [['Prices', "| Room | Price |\n|---|---|\n| Suite | 90 |\n\n- Breakfast"]]);

    get(readerUrl($bot, $document))
        ->assertSee('<table>', escape: false)
        ->assertSee('<li>Breakfast</li>', escape: false);
});

it('cannot be attacked through the text of a document', function (): void {
    $bot = Bot::factory()->create();
    $document = documentWith($bot, [
        ['<script>alert("heading")</script>', "<script>alert('body')</script>\n\n<img src=x onerror=alert(1)>\n\n[click](javascript:alert(1))"],
    ], ['title' => '<script>alert("title")</script>']);

    get(readerUrl($bot, $document))
        ->assertOk()
        ->assertDontSee('<script>alert', escape: false)
        ->assertDontSee('onerror', escape: false)
        ->assertDontSee('javascript:alert', escape: false);
});

it('shows only published text, not the drafts of a running re-index', function (): void {
    $bot = Bot::factory()->create();
    $document = documentWith($bot, [['A', 'Live text.']]);
    Chunk::factory()->for($document)->for($bot)->create(['position' => 1, 'content' => 'Draft text.']);

    get(readerUrl($bot, $document))
        ->assertSee('Live text.')
        ->assertDontSee('Draft text.');
});

it('stays open while the document is being re-indexed', function (): void {
    $bot = Bot::factory()->create();
    $document = documentWith($bot, [['A', 'Live text.']], ['status' => DocumentStatus::Processing]);

    get(readerUrl($bot, $document))->assertOk();
});

it('lists the readable documents of the bot only', function (): void {
    $bot = Bot::factory()->create();
    $document = documentWith($bot, [['A', 'Text.']]);
    documentWith($bot, [['A', 'Text.']], ['title' => 'Second Guide']);
    Document::factory()->for($bot)->create(['title' => 'Still Processing']);
    Document::factory()->for($bot)->ready()->fromUrl('https://example.com')->create(['title' => 'A Web Page']);
    documentWith(Bot::factory()->create(), [['A', 'Text.']], ['title' => 'Other Bot Guide']);

    get(readerUrl($bot, $document))
        ->assertSee('Second Guide')
        ->assertDontSee('Still Processing')
        ->assertDontSee('A Web Page')
        ->assertDontSee('Other Bot Guide');
});

it('runs the same number of queries however long the document is', function (): void {
    $bot = Bot::factory()->create();
    $short = documentWith($bot, [['A', 'One.']]);
    $long = documentWith($bot, array_map(fn (int $index): array => ["A > Section {$index}", 'Text.'], range(1, 30)));

    $countQueries = function (Document $document) use ($bot): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        get(readerUrl($bot, $document))->assertOk();

        return count(DB::getQueryLog());
    };

    expect($countQueries($long))->toBe($countQueries($short));
});

describe('access', function (): void {
    it('does not open the document of another bot', function (): void {
        $document = documentWith(Bot::factory()->create(), [['A', 'Secret.']]);

        get(readerUrl(Bot::factory()->create(), $document))->assertNotFound();
    });

    it('does not open a document that was never indexed', function (): void {
        $bot = Bot::factory()->create();

        get(readerUrl($bot, Document::factory()->for($bot)->create()))->assertNotFound();
    });

    it('leaves a web page at its own address', function (): void {
        $bot = Bot::factory()->create();
        $document = Document::factory()->for($bot)->ready()->fromUrl('https://example.com')->create();

        get(readerUrl($bot, $document))->assertNotFound();
    });

    it('returns 404 for an unknown bot', function (): void {
        get(route('reader.show', ['pk_unknown', 1]))->assertNotFound();
    });

    it('returns 404 for a number the database cannot hold', function (): void {
        get('/read/'.Bot::factory()->create()->public_key.'/99999999999999999999999')->assertNotFound();
    });
});
