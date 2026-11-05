<?php

declare(strict_types=1);

use App\Actions\Ingestion\IngestFile;
use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Jobs\Ingestion\ChunkText;
use App\Jobs\Ingestion\EmbedChunks;
use App\Jobs\Ingestion\ExtractText;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Sync-queue failures surface as exceptions after the job has recorded them.
 */
function ingestExpectingFailure(Bot $bot, string $path): Document
{
    try {
        app(IngestFile::class)->handle($bot, $path);
    } catch (Throwable) {
        return Document::query()->latest('id')->firstOrFail();
    }

    throw new LogicException('Ingestion was expected to fail.');
}

it('indexes a Markdown file into searchable chunks', function (): void {
    Storage::fake('local');
    fakeEmbeddings();
    $bot = Bot::factory()->create();

    $document = app(IngestFile::class)->handle($bot, markdownFile("# Pets\n\nDogs are welcome.\n\n## Fees\n\nA fee of 40 dollars applies."), 'Pets policy');

    $document->refresh();
    $chunks = Chunk::query()->published()->orderBy('position')->get();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->source_type)->toBe(DocumentSourceType::Text)
        ->and($document->title)->toBe('Pets policy')
        ->and($document->chunk_count)->toBe(2)
        ->and($document->checksum)->toHaveLength(64)
        ->and($document->extraction)->toBeNull()
        ->and($chunks)->toHaveCount(2)
        ->and($chunks[1]->heading)->toBe('Pets > Fees')
        ->and($chunks[1]->bot_id)->toBe($bot->id)
        ->and($chunks[1]->embedding)->toHaveCount(1536);

    Storage::disk('local')->assertExists((string) $document->file_path);
});

it('embeds each chunk together with its heading trail', function (): void {
    Storage::fake('local');
    $embeddings = fakeEmbeddings();

    app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile("# Parking\n\n## Harbor View\n\nValet only."));

    expect($embeddings->batches)->toBe([["Parking > Harbor View\n\nValet only."]]);
});

it('derives a title from the file name when none is given', function (): void {
    Storage::fake('local');
    fakeEmbeddings();

    $document = app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile('Text.', 'check-in-policy.md'));

    expect($document->title)->toBe('Check In Policy');
});

it('makes chunks findable through the generated full-text column', function (): void {
    Storage::fake('local');
    fakeEmbeddings();

    app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile("# Parking\n\nThe garage closes at midnight."));

    $matches = fn (string $query): int => Chunk::query()
        ->whereRaw("tsv @@ websearch_to_tsquery('english', ?)", [$query])
        ->count();

    expect($matches('garage closing'))->toBe(1)
        ->and($matches('parking'))->toBe(1)
        ->and($matches('swimming'))->toBe(0);
});

it('sends chunks to the embedding model in configured batches', function (): void {
    Storage::fake('local');
    config()->set('docschat.ai.embeddings.batch_size', 2);
    $embeddings = fakeEmbeddings();

    $sections = implode("\n\n", array_map(fn (int $number): string => "# Section {$number}\n\nBody {$number}.", range(1, 5)));
    app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile($sections));

    expect(array_map(count(...), $embeddings->batches))->toBe([2, 2, 1])
        ->and(Chunk::query()->published()->count())->toBe(5);
});

it('writes the vectors of many chunks in a single statement', function (): void {
    Storage::fake('local');
    fakeEmbeddings();
    $sections = implode("\n\n", array_map(fn (int $number): string => "# S{$number}\n\nBody {$number}.", range(1, 40)));
    $vectorWrites = 0;

    DB::listen(function (QueryExecuted $query) use (&$vectorWrites): void {
        $vectorWrites += str_starts_with($query->sql, 'update chunks') ? 1 : 0;
    });

    app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile($sections));

    expect($vectorWrites)->toBe(1)
        ->and(Chunk::query()->published()->count())->toBe(40);
});

it('does nothing when the same content is ingested again', function (): void {
    Storage::fake('local');
    $embeddings = fakeEmbeddings();
    $bot = Bot::factory()->create();
    $path = markdownFile("# Pets\n\nDogs are welcome.");

    app(IngestFile::class)->handle($bot, $path);
    $chunkIds = Chunk::query()->pluck('id')->all();

    $document = app(IngestFile::class)->handle($bot, $path);

    expect($embeddings->batches)->toHaveCount(1)
        ->and(Chunk::query()->pluck('id')->all())->toBe($chunkIds)
        ->and($document->refresh()->status)->toBe(DocumentStatus::Ready)
        ->and(Document::query()->count())->toBe(1);
});

it('replaces all chunks when the content changes', function (): void {
    Storage::fake('local');
    fakeEmbeddings();
    $bot = Bot::factory()->create();

    app(IngestFile::class)->handle($bot, markdownFile("# Pets\n\nDogs are welcome.\n\n# Fees\n\nForty dollars."));
    $document = app(IngestFile::class)->handle($bot, markdownFile("# Pets\n\nOnly cats are welcome now."));

    expect($document->refresh()->chunk_count)->toBe(1)
        ->and(Document::query()->count())->toBe(1)
        ->and(Chunk::query()->pluck('content')->all())->toBe(['Only cats are welcome now.']);
});

it('keeps the published chunks searchable when embedding a new version fails', function (): void {
    Storage::fake('local');
    $embeddings = fakeEmbeddings();
    $bot = Bot::factory()->create();

    app(IngestFile::class)->handle($bot, markdownFile("# Pets\n\nDogs are welcome."));
    $embeddings->failWith(new RuntimeException('Provider is down.'));

    $document = ingestExpectingFailure($bot, markdownFile("# Pets\n\nOnly cats are welcome now."));

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error)->toBe('Provider is down.')
        ->and($document->extraction)->toBeNull()
        ->and(Chunk::query()->pluck('content')->all())->toBe(['Dogs are welcome.']);
});

it('indexes the new version after a failed attempt instead of treating it as unchanged', function (): void {
    Storage::fake('local');
    $embeddings = fakeEmbeddings();
    $bot = Bot::factory()->create();
    $changed = "# Pets\n\nOnly cats are welcome now.";

    app(IngestFile::class)->handle($bot, markdownFile("# Pets\n\nDogs are welcome."));
    $embeddings->failWith(new RuntimeException('Provider is down.'));
    ingestExpectingFailure($bot, markdownFile($changed));

    fakeEmbeddings();
    app(IngestFile::class)->handle($bot, markdownFile($changed));

    expect(Chunk::query()->pluck('content')->all())->toBe(['Only cats are welcome now.']);
});

it('finishes a retried embedding job without duplicating chunks', function (): void {
    Storage::fake('local');
    $embeddings = fakeEmbeddings();

    $document = app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile("# Pets\n\nDogs are welcome."));
    EmbedChunks::dispatchSync($document->refresh());

    expect($embeddings->batches)->toHaveCount(1)
        ->and(Chunk::query()->count())->toBe(1)
        ->and($document->refresh()->status)->toBe(DocumentStatus::Ready);
});

it('does not start a second pipeline while one is in flight', function (): void {
    Storage::fake('local');
    Queue::fake([ExtractText::class]);
    $bot = Bot::factory()->create();
    $path = markdownFile('Text.');

    app(IngestFile::class)->handle($bot, $path);
    app(IngestFile::class)->handle($bot, $path);

    Queue::assertPushed(ExtractText::class, 1);
});

it('runs the pipeline on the ingestion queue', function (string $job): void {
    Queue::fake([$job]);

    $job::dispatch(Document::factory()->create());

    Queue::assertPushedOn('ingestion', $job);
})->with([ExtractText::class, ChunkText::class, EmbedChunks::class]);

it('keeps documents of different bots apart', function (): void {
    Storage::fake('local');
    fakeEmbeddings();
    $path = markdownFile('Shared text.');

    app(IngestFile::class)->handle(Bot::factory()->create(), $path);
    app(IngestFile::class)->handle(Bot::factory()->create(), $path);

    expect(Document::query()->count())->toBe(2)
        ->and(Chunk::query()->distinct()->count('bot_id'))->toBe(2);
});

it('rejects unsupported and unreadable files', function (string $path): void {
    app(IngestFile::class)->handle(Bot::factory()->create(), $path);
})->throws(UnprocessableSourceException::class)->with([
    'missing file' => '/nonexistent/guide.md',
    'unsupported extension' => fn (): string => markdownFile('a,b', 'sheet.csv'),
    'text renamed to pdf' => fn (): string => markdownFile('Not a PDF.', 'fake.pdf'),
]);

it('records binary content in a text file as a failure', function (): void {
    Storage::fake('local');
    fakeEmbeddings();

    $document = ingestExpectingFailure(Bot::factory()->create(), markdownFile("\xFF\xFE broken"));

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error)->toBe('The file is not valid UTF-8 text.');
});

describe('PDF files', function (): void {
    it('keeps the page number and headings of each chunk', function (): void {
        Storage::fake('local');
        fakeEmbeddings();

        $document = app(IngestFile::class)->handle(Bot::factory()->create(), base_path('tests/Fixtures/two-page.pdf'));

        $towels = Chunk::query()->where('content', 'like', '%Fresh towels%')->firstOrFail();
        $hours = Chunk::query()->where('content', 'like', '%opens at 7:00%')->firstOrFail();

        expect($document->refresh()->source_type)->toBe(DocumentSourceType::Pdf)
            ->and($document->status)->toBe(DocumentStatus::Ready)
            ->and($hours->page)->toBe(1)
            ->and($hours->heading)->toBe('Pool Guide > Opening Hours')
            ->and($towels->page)->toBe(2)
            ->and($towels->heading)->toBe('Pool Guide > Towels');
    });

    it('fails a document that pdftotext cannot read', function (): void {
        Storage::fake('local');
        fakeEmbeddings();
        Process::fake(['*pdftotext*' => Process::result(exitCode: 1)]);

        $document = ingestExpectingFailure(Bot::factory()->create(), base_path('tests/Fixtures/two-page.pdf'));

        expect($document->status)->toBe(DocumentStatus::Failed)
            ->and($document->error)->toContain('could not be read');
    });

    it('fails a scanned document without a text layer', function (): void {
        Storage::fake('local');
        fakeEmbeddings();
        Process::fake(['*pdftotext*' => Process::result('<doc><page><flow></flow></page></doc>')]);

        $document = ingestExpectingFailure(Bot::factory()->create(), base_path('tests/Fixtures/two-page.pdf'));

        expect($document->status)->toBe(DocumentStatus::Failed)
            ->and($document->error)->toContain('Scanned documents are not supported');
    });
});
