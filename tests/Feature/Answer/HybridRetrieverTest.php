<?php

declare(strict_types=1);

use App\Data\Answer\RetrievedChunk;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Document;
use App\Services\Answer\HybridRetriever;
use Illuminate\Support\Facades\DB;

/**
 * A unit vector pointing along the first axis, tilted towards the second one.
 * Its cosine similarity to the first axis is exactly the given value.
 *
 * @return list<float>
 */
function vectorWithSimilarity(float $similarity): array
{
    $vector = array_fill(0, 1536, 0.0);
    $vector[0] = $similarity;
    $vector[1] = sqrt(1 - $similarity ** 2);

    return $vector;
}

/**
 * @return list<float>
 */
function queryVector(): array
{
    return vectorWithSimilarity(1.0);
}

function chunkFor(Bot $bot, float $similarity, string $content = 'Plain filler text.'): Chunk
{
    return Chunk::factory()
        ->for(Document::factory()->for($bot))
        ->for($bot)
        ->create(['content' => $content, 'heading' => null, 'embedding' => vectorWithSimilarity($similarity)]);
}

/**
 * @return list<string>
 */
function rankedContents(Bot $bot, string $query = 'unmatched'): array
{
    return array_map(
        fn (RetrievedChunk $chunk): string => $chunk->chunk->content,
        app(HybridRetriever::class)->retrieve($bot, $query)->chunks,
    );
}

it('ranks chunks by cosine similarity when no text matches', function (): void {
    fakeEmbeddings()->returnVector(queryVector());
    $bot = Bot::factory()->create();

    chunkFor($bot, 0.2, 'far');
    chunkFor($bot, 0.9, 'close');
    chunkFor($bot, 0.5, 'middle');

    expect(rankedContents($bot))->toBe(['close', 'middle', 'far']);
});

it('reports the cosine similarity of each chunk and the best one overall', function (): void {
    fakeEmbeddings()->returnVector(queryVector());
    $bot = Bot::factory()->create();

    chunkFor($bot, 0.9);
    chunkFor($bot, 0.5);

    $result = app(HybridRetriever::class)->retrieve($bot, 'unmatched');

    expect($result->bestSimilarity)->toEqualWithDelta(0.9, 0.001)
        ->and($result->chunks[1]->similarity)->toEqualWithDelta(0.5, 0.001);
});

it('lifts a chunk that also matches by exact wording above a closer vector', function (): void {
    fakeEmbeddings()->returnVector(queryVector());
    $bot = Bot::factory()->create();

    chunkFor($bot, 0.9, 'Nearest by meaning.');
    chunkFor($bot, 0.8, 'The valet garage has a height limit.');

    expect(rankedContents($bot, 'valet garage'))->toBe([
        'The valet garage has a height limit.',
        'Nearest by meaning.',
    ]);
});

it('computes the Reciprocal Rank Fusion score from both rankings', function (): void {
    fakeEmbeddings()->returnVector(queryVector());
    $bot = Bot::factory()->create();

    chunkFor($bot, 0.9, 'Nearest by meaning.');
    chunkFor($bot, 0.8, 'The valet garage has a height limit.');

    [$both, $semanticOnly] = app(HybridRetriever::class)->retrieve($bot, 'valet')->chunks;

    expect($both->score)->toEqualWithDelta(1 / 62 + 1 / 61, 0.000001)
        ->and($semanticOnly->score)->toEqualWithDelta(1 / 61, 0.000001);
});

it('finds a chunk through full-text search when it is outside the vector candidates', function (): void {
    fakeEmbeddings()->returnVector(queryVector());
    config()->set('docschat.retrieval.candidates', 2);
    $bot = Bot::factory()->create();

    chunkFor($bot, 0.9, 'First.');
    chunkFor($bot, 0.8, 'Second.');
    chunkFor($bot, 0.1, 'The ferry leaves at 21:30.');

    $result = app(HybridRetriever::class)->retrieve($bot, 'ferry');
    $fullTextOnly = collect($result->chunks)->firstWhere('similarity', null);

    expect($fullTextOnly?->chunk->content)->toBe('The ferry leaves at 21:30.');
});

it('keeps only the configured number of chunks', function (): void {
    fakeEmbeddings()->returnVector(queryVector());
    config()->set('docschat.retrieval.keep', 2);
    $bot = Bot::factory()->create();

    foreach ([0.9, 0.8, 0.7] as $similarity) {
        chunkFor($bot, $similarity, (string) $similarity);
    }

    expect(rankedContents($bot))->toBe(['0.9', '0.8']);
});

it('never returns chunks of another bot', function (): void {
    fakeEmbeddings()->returnVector(queryVector());
    $bot = Bot::factory()->create();

    chunkFor(Bot::factory()->create(), 1.0, 'The valet of another hotel.');
    chunkFor($bot, 0.2, 'Ours.');

    expect(rankedContents($bot, 'valet'))->toBe(['Ours.']);
});

it('ignores draft chunks that are not published yet', function (): void {
    fakeEmbeddings()->returnVector(queryVector());
    $bot = Bot::factory()->create();

    Chunk::factory()->for(Document::factory()->for($bot))->for($bot)->create(['content' => 'Draft valet text.']);

    expect(rankedContents($bot, 'valet'))->toBe([]);
});

it('returns no chunks and no similarity for a bot without documents', function (): void {
    fakeEmbeddings();

    $result = app(HybridRetriever::class)->retrieve(Bot::factory()->create(), 'valet');

    expect($result->chunks)->toBe([])
        ->and($result->bestSimilarity)->toBeNull();
});

it('loads chunk documents without the extraction payload or extra queries per chunk', function (): void {
    fakeEmbeddings()->returnVector(queryVector());
    $bot = Bot::factory()->create();

    foreach ([0.9, 0.8, 0.7, 0.6] as $similarity) {
        chunkFor($bot, $similarity);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $result = app(HybridRetriever::class)->retrieve($bot, 'unmatched');

    $titles = array_map(fn (RetrievedChunk $chunk): string => $chunk->chunk->document->title, $result->chunks);

    expect($titles)->toHaveCount(4)
        ->and(DB::getQueryLog())->toHaveCount(4)
        ->and(collect(DB::getQueryLog())->pluck('query')->implode(' '))->not->toContain('"documents".*');
});

it('records the embedding usage of the query', function (): void {
    fakeEmbeddings();

    $usage = app(HybridRetriever::class)->retrieve(Bot::factory()->create(), 'where can I park')->usage;

    expect($usage->model)->toBe('fake-embedding')
        ->and($usage->promptTokens)->toBe(4);
});
