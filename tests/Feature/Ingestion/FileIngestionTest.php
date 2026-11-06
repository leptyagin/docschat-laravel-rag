<?php

declare(strict_types=1);

use App\Actions\Ingestion\EmbedDocumentChunks;
use App\Actions\Ingestion\IngestFile;
use App\Actions\Ingestion\ReindexDocument;
use App\Actions\Ingestion\StartIngestion;
use App\Ai\Contracts\EmbeddingModel;
use App\Ai\Data\EmbeddingResult;
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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

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

it('stores and publishes the vectors of many chunks in two statements', function (): void {
    Storage::fake('local');
    fakeEmbeddings();
    $sections = implode("\n\n", array_map(fn (int $number): string => "# S{$number}\n\nBody {$number}.", range(1, 40)));
    $vectorWrites = 0;

    DB::listen(function (QueryExecuted $query) use (&$vectorWrites): void {
        $vectorWrites += str_starts_with($query->sql, 'update chunks') ? 1 : 0;
    });

    app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile($sections));

    expect($vectorWrites)->toBe(2)
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
        ->and($document->error)->toBe('Processing failed after several attempts. Please try again later.')
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

it('treats a duplicate embedding job as harmless', function (): void {
    $bot = Bot::factory()->create();
    $document = Document::factory()->for($bot)->create(['status' => DocumentStatus::Processing]);
    Chunk::factory()->for($document)->for($bot)->create(['content' => 'Draft.']);

    $duplicate = new class implements EmbeddingModel
    {
        private bool $isRunning = false;

        public function name(): string
        {
            return 'fake-embedding';
        }

        public function embed(array $texts): EmbeddingResult
        {
            if (! $this->isRunning) {
                $this->isRunning = true;

                new EmbedDocumentChunks($this, 100)->handle(Document::query()->firstOrFail());
            }

            return new EmbeddingResult(array_fill(0, count($texts), array_pad([1.0], 1536, 0.0)), 'fake-embedding', 1);
        }
    };

    new EmbedDocumentChunks($duplicate, 100)->handle($document);

    expect(Chunk::query()->published()->pluck('content')->all())->toBe(['Draft.'])
        ->and($document->refresh()->status)->toBe(DocumentStatus::Ready);
});

it('resumes embedding without paying again for finished batches', function (): void {
    Storage::fake('local');
    config()->set('docschat.ai.embeddings.batch_size', 1);
    $failing = new class implements EmbeddingModel
    {
        /** @var list<string> */
        public array $embedded = [];

        public function name(): string
        {
            return 'fake-embedding';
        }

        public function embed(array $texts): EmbeddingResult
        {
            if ($this->embedded !== [] && $texts[0] === "B\n\nSecond." && count($this->embedded) === 1) {
                $this->embedded[] = 'failed';

                throw new RuntimeException('Provider is down.');
            }

            $this->embedded[] = $texts[0];

            return new EmbeddingResult([array_pad([1.0], 1536, 0.0)], 'fake-embedding', 1);
        }
    };
    app()->instance(EmbeddingModel::class, $failing);
    Queue::fake([EmbedChunks::class]);

    $document = app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile("# A\n\nFirst.\n\n# B\n\nSecond."));
    ChunkText::dispatchSync($document->refresh());

    $embed = app(EmbedDocumentChunks::class);

    expect(fn () => $embed->handle($document))->toThrow(RuntimeException::class);

    $embed->handle($document);

    expect($failing->embedded)->toBe(["A\n\nFirst.", 'failed', "B\n\nSecond."])
        ->and(Chunk::query()->published()->count())->toBe(2);
});

it('splits a large document over several runs of the job', function (): void {
    Storage::fake('local');
    config()->set('docschat.ai.embeddings.batch_size', 1);
    fakeEmbeddings();

    $sections = implode("\n\n", array_map(fn (int $number): string => "# S{$number}\n\nBody {$number}.", range(1, 6)));
    $document = app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile($sections));

    expect($document->refresh()->status)->toBe(DocumentStatus::Ready)
        ->and(Chunk::query()->published()->count())->toBe(6);
});

it('retries a transient failure with backoff through a real worker', function (): void {
    config()->set('queue.default', 'database');
    Storage::fake('local');
    $embeddings = fakeEmbeddings()->failWith(new RuntimeException('Provider is down.'));
    $document = Document::factory()->create(['status' => DocumentStatus::Processing]);
    Chunk::factory()->for($document)->for($document->bot)->create();

    EmbedChunks::dispatch($document);

    artisan('queue:work', ['--once' => true, '--queue' => 'ingestion', '--stop-when-empty' => true]);

    $job = DB::table('jobs')->firstOrFail();

    expect($job->attempts)->toBe(1)
        ->and($job->available_at)->toBeGreaterThan(now()->getTimestamp())
        ->and($document->refresh()->status)->toBe(DocumentStatus::Processing);
});

it('fails a permanent problem at once, without retrying', function (): void {
    config()->set('queue.default', 'database');
    Storage::fake('local');
    $document = Document::factory()->create(['file_path' => 'missing.md']);

    ExtractText::dispatch($document);

    artisan('queue:work', ['--once' => true, '--queue' => 'ingestion', '--stop-when-empty' => true]);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and($document->refresh()->status)->toBe(DocumentStatus::Failed)
        ->and($document->error)->toBe('The uploaded file is missing.');
});

it('marks the document failed when the queue is unavailable', function (): void {
    Storage::fake('local');
    Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('Redis is down.'));

    $path = markdownFile('Text.');
    $bot = Bot::factory()->create();

    expect(fn () => app(IngestFile::class)->handle($bot, $path))->toThrow(RuntimeException::class);

    expect(Document::query()->firstOrFail()->status)->toBe(DocumentStatus::Failed);
});

it('re-embeds unchanged text after the embedding model has changed', function (): void {
    Storage::fake('local');
    fakeEmbeddings();
    $bot = Bot::factory()->create();
    $document = app(IngestFile::class)->handle($bot, markdownFile('Text.'));
    $document->refresh()->update(['embedding_model' => 'an-older-model']);

    $embeddings = fakeEmbeddings();
    app(StartIngestion::class)->handle($document);

    expect($embeddings->batches)->toHaveCount(1)
        ->and($document->refresh()->embedding_model)->toBe('fake-embedding');
});

it('re-indexes unchanged text on request', function (): void {
    Storage::fake('local');
    fakeEmbeddings();
    $document = app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile('Text.'));

    $embeddings = fakeEmbeddings();
    app(ReindexDocument::class)->handle($document->refresh());

    expect($embeddings->batches)->toHaveCount(1);
});

it('takes over a document whose jobs were lost', function (): void {
    Storage::fake('local');
    Queue::fake([ExtractText::class]);
    $bot = Bot::factory()->create();
    $path = markdownFile('Text.', 'lost.md');

    app(IngestFile::class)->handle($bot, $path);
    Document::query()->update(['updated_at' => now()->subHours(2)]);
    app(IngestFile::class)->handle($bot, $path);

    Queue::assertPushed(ExtractText::class, 2);
});

it('keeps files apart whose names slug alike or to nothing', function (array $names): void {
    Storage::fake('local');
    fakeEmbeddings();
    $bot = Bot::factory()->create();

    foreach ($names as $name) {
        app(IngestFile::class)->handle($bot, markdownFile("Text of {$name}.", $name));
    }

    expect(Document::query()->count())->toBe(2);
})->with([
    'non-Latin names' => [['日本語.md', '中文.md']],
    'space and dash' => [['a b.md', 'a-b.md']],
]);

it('shortens a title that does not fit the column', function (): void {
    Storage::fake('local');
    fakeEmbeddings();

    $document = app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile('Text.'), str_repeat('t', 300));

    expect(mb_strlen($document->refresh()->title))->toBeLessThanOrEqual(255);
});

it('rejects a file above the size limit', function (): void {
    config()->set('docschat.ingestion.max_file_bytes', 5);

    app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile('More than five bytes.'));
})->throws(UnprocessableSourceException::class, 'too large');

it('recognises a heading after a byte order mark', function (): void {
    Storage::fake('local');
    fakeEmbeddings();

    app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile("\u{FEFF}# Pets\n\nDogs."));

    expect(Chunk::query()->firstOrFail()->heading)->toBe('Pets');
});

it('survives NUL bytes in a text file', function (): void {
    Storage::fake('local');
    fakeEmbeddings();

    $document = app(IngestFile::class)->handle(Bot::factory()->create(), markdownFile("Nul\u{0000} byte."));

    expect($document->refresh()->status)->toBe(DocumentStatus::Ready)
        ->and(Chunk::query()->firstOrFail()->content)->toBe('Nul byte.');
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

    it('joins a heading that wraps over two lines', function (): void {
        Storage::fake('local');
        fakeEmbeddings();
        Process::fake(['*pdftotext*' => Process::result(<<<'XML'
        <doc><page><flow>
        <block><line yMin="0" yMax="18"><word>Rules</word><word>for</word></line><line yMin="20" yMax="38"><word>Group</word><word>Bookings</word></line></block>
        <block><line yMin="50" yMax="60"><word>Body</word><word>text</word><word>long</word><word>enough</word><word>to</word><word>set</word><word>the</word><word>size.</word></line></block>
        </flow></page></doc>
        XML)]);

        app(IngestFile::class)->handle(Bot::factory()->create(), base_path('tests/Fixtures/two-page.pdf'));

        expect(Chunk::query()->firstOrFail()->heading)->toBe('Rules for Group Bookings');
    });

    it('counts heading words in any script', function (): void {
        Storage::fake('local');
        fakeEmbeddings();
        $longLine = implode('', array_map(fn (int $i): string => '<word>слово</word>', range(1, 20)));
        Process::fake(['*pdftotext*' => Process::result(<<<XML
        <doc><page><flow>
        <block><line yMin="0" yMax="18">{$longLine}</line></block>
        <block><line yMin="50" yMax="60"><word>Обычный</word><word>текст</word><word>документа</word><word>достаточной</word><word>длины</word><word>для</word><word>размера.</word></line></block>
        </flow></page></doc>
        XML)]);

        app(IngestFile::class)->handle(Bot::factory()->create(), base_path('tests/Fixtures/two-page.pdf'));

        expect(Chunk::query()->firstOrFail()->heading)->toBeNull();
    });

    it('refuses a PDF above the page limit', function (): void {
        Storage::fake('local');
        fakeEmbeddings();
        config()->set('docschat.ingestion.max_pdf_pages', 1);

        $document = ingestExpectingFailure(Bot::factory()->create(), base_path('tests/Fixtures/two-page.pdf'));

        expect($document->error)->toBe('The PDF has more than 1 pages.');
    });

    it('fails a document whose layout is broken', function (): void {
        Storage::fake('local');
        fakeEmbeddings();
        Process::fake(['*pdftotext*' => Process::result('<doc><page><line>')]);

        $document = ingestExpectingFailure(Bot::factory()->create(), base_path('tests/Fixtures/two-page.pdf'));

        expect($document->status)->toBe(DocumentStatus::Failed);
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

it('stops reading a PDF whose text layer is too large', function (): void {
    config()->set('docschat.ingestion.max_pdf_layout_bytes', 100);
    $bot = Bot::factory()->create();

    $document = ingestExpectingFailure($bot, database_path('seed-docs/accessibility.pdf'));

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error)->toContain('too much text');
});
