<?php

declare(strict_types=1);

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Jobs\Ingestion\ExtractText;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('lists the documents of the managed bot with their status and error', function (): void {
    actingAsAdmin();
    $bot = managedBot();
    Document::factory()->for($bot)->ready()->create(['title' => 'Pets Policy']);
    Document::factory()->for($bot)->create(['title' => 'Broken', 'status' => DocumentStatus::Failed, 'error' => 'The PDF could not be read.']);
    Document::factory()->create(['title' => 'Foreign Document']);

    get(route('admin.documents.index'))
        ->assertOk()
        ->assertSee('Pets Policy')
        ->assertSee('The PDF could not be read.')
        ->assertDontSee('Foreign Document');
});

it('refreshes the page while a document is being indexed', function (): void {
    actingAsAdmin();
    Document::factory()->for(managedBot())->create(['status' => DocumentStatus::Processing]);

    get(route('admin.documents.index'))->assertSee('http-equiv="refresh"', escape: false);
});

it('queues an uploaded file under its original name', function (): void {
    actingAsAdmin();
    Storage::fake('local');
    Queue::fake([ExtractText::class]);
    $bot = managedBot();

    from(route('admin.documents.index'))
        ->post(route('admin.documents.store'), ['file' => UploadedFile::fake()->createWithContent('guest-guide.md', '# Guide')])
        ->assertRedirect(route('admin.documents.index'))
        ->assertSessionHas('status');

    $document = Document::query()->firstOrFail();

    expect($document->bot_id)->toBe($bot->id)
        ->and($document->title)->toBe('Guest Guide')
        ->and($document->source_type)->toBe(DocumentSourceType::Text);

    Queue::assertPushed(ExtractText::class);
});

it('rejects a file of the wrong type', function (): void {
    actingAsAdmin();
    managedBot();

    post(route('admin.documents.store'), ['file' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(0);
});

it('shows a file that only pretends to be a PDF as a form error', function (): void {
    actingAsAdmin();
    Storage::fake('local');
    managedBot();

    post(route('admin.documents.store'), ['file' => UploadedFile::fake()->createWithContent('fake.pdf', 'Not a PDF.')])
        ->assertSessionHasErrors('file');
});

it('queues a web page', function (): void {
    actingAsAdmin();
    Queue::fake([ExtractText::class]);
    managedBot();

    post(route('admin.documents.store-url'), ['url' => 'https://hotel.example/faq'])->assertSessionHas('status');

    expect(Document::query()->firstOrFail()->source_url)->toBe('https://hotel.example/faq');
});

it('rejects a URL that is not public http', function (string $url): void {
    actingAsAdmin();
    managedBot();

    post(route('admin.documents.store-url'), ['url' => $url])->assertSessionHasErrors('url');
})->with(['ftp://hotel.example/file', 'not a url', 'https://user:secret@hotel.example/']);

it('re-indexes a document', function (): void {
    actingAsAdmin();
    Queue::fake([ExtractText::class]);
    $document = Document::factory()->for(managedBot())->ready()->create();

    post(route('admin.documents.reindex', $document))->assertSessionHas('status');

    expect($document->refresh()->checksum)->toBeNull()
        ->and($document->status)->toBe(DocumentStatus::Pending);

    Queue::assertPushed(ExtractText::class);
});

it('deletes a document with its chunks and its file', function (): void {
    actingAsAdmin();
    Storage::fake('local');
    Storage::disk('local')->put('documents/guide.md', 'Text');
    $bot = managedBot();
    $document = Document::factory()->for($bot)->create(['file_path' => 'documents/guide.md']);
    $chunk = Chunk::factory()->for($document)->for($bot)->create();

    delete(route('admin.documents.destroy', $document))->assertSessionHas('status');

    assertModelMissing($document);
    assertModelMissing($chunk);
    Storage::disk('local')->assertMissing('documents/guide.md');
});

it('returns 404 for a document of another bot', function (): void {
    actingAsAdmin();
    managedBot();
    $foreign = Document::factory()->for(Bot::factory())->create();

    delete(route('admin.documents.destroy', $foreign))->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});
