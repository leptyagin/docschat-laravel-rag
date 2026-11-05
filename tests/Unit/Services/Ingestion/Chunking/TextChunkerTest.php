<?php

declare(strict_types=1);

use App\Data\Ingestion\ChunkDraft;
use App\Data\Ingestion\ExtractedDocument;
use App\Data\Ingestion\ExtractedPage;
use App\Services\Ingestion\Chunking\EstimatedTokenCounter;
use App\Services\Ingestion\Chunking\TextChunker;

function chunker(int $targetTokens = 600, int $overlapTokens = 80): TextChunker
{
    return new TextChunker(new EstimatedTokenCounter, $targetTokens, $overlapTokens);
}

/**
 * @return list<ChunkDraft>
 */
function chunkText(string $text, ?TextChunker $chunker = null): array
{
    return ($chunker ?? chunker())->chunk(new ExtractedDocument([new ExtractedPage($text)]));
}

function sentences(int $count, string $prefix = 'Sentence'): string
{
    return implode(' ', array_map(
        fn (int $number): string => "{$prefix} number {$number} ends right here.",
        range(1, $count),
    ));
}

it('starts a new chunk at every heading', function (): void {
    $chunks = chunkText("# Pets\n\nDogs are welcome.\n\n# Parking\n\nThe garage is open.");

    expect($chunks)->toHaveCount(2)
        ->and($chunks[0]->heading)->toBe('Pets')
        ->and($chunks[0]->content)->toBe('Dogs are welcome.')
        ->and($chunks[1]->heading)->toBe('Parking')
        ->and($chunks[1]->content)->toBe('The garage is open.');
});

it('keeps the full heading trail and drops closed levels', function (): void {
    $chunks = chunkText("# Parking\n\n## Harbor View\n\nValet only.\n\n### Fees\n\n30 dollars.\n\n## Old Town\n\nStreet parking.");

    expect(array_map(fn (ChunkDraft $chunk): ?string => $chunk->heading, $chunks))->toBe([
        'Parking > Harbor View',
        'Parking > Harbor View > Fees',
        'Parking > Old Town',
    ]);
});

it('recognises a heading directly followed by text', function (): void {
    $chunks = chunkText("## Spa\nOpen daily from 9 to 18.");

    expect($chunks[0]->heading)->toBe('Spa')
        ->and($chunks[0]->content)->toBe('Open daily from 9 to 18.');
});

it('skips headings that have no text of their own', function (): void {
    expect(chunkText("# Empty\n\n## Filled\n\nSome text."))->toHaveCount(1);
});

it('keeps text before the first heading without a heading', function (): void {
    expect(chunkText("Welcome.\n\n# Pets\n\nDogs.")[0]->heading)->toBeNull();
});

it('splits a long section so that no chunk exceeds the target', function (): void {
    $chunks = chunkText("# Rules\n\n".sentences(400));

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect($chunk->tokenCount)->toBeLessThanOrEqual(600)
            ->and($chunk->heading)->toBe('Rules');
    }
});

it('fills chunks close to the target instead of leaving them short', function (): void {
    $chunks = chunkText(sentences(400));

    expect($chunks[0]->tokenCount)->toBeGreaterThan(500);
});

it('repeats the end of a chunk at the start of the next one', function (): void {
    $paragraphs = implode("\n\n", array_map(fn (int $number): string => sentences(5, "Part {$number}"), range(1, 60)));

    [$first, $second] = chunkText($paragraphs);

    $overlap = (string) strstr($second->content, "\n\n", before_needle: true);

    expect($overlap)->not->toBeEmpty()
        ->and((string) preg_replace('/\s+/', ' ', $first->content))->toEndWith($overlap);
});

it('starts the overlap on a sentence boundary within the budget', function (): void {
    [, $second] = chunkText(sentences(400));

    expect($second->content)->toMatch('/^Sentence number \d+ ends right here\./');
});

it('does not overlap across headings', function (): void {
    $chunks = chunkText("# First\n\n".sentences(20)."\n\n# Second\n\nFresh topic.");

    expect(end($chunks)->content)->toBe('Fresh topic.');
});

it('splits text with no sentence boundaries by words', function (): void {
    $chunks = chunkText(str_repeat('word ', 2000));

    foreach ($chunks as $chunk) {
        expect($chunk->tokenCount)->toBeLessThanOrEqual(600);
    }
});

it('splits a single unbroken string of any length', function (): void {
    $chunks = chunkText(str_repeat('x', 10_000));

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect($chunk->tokenCount)->toBeLessThanOrEqual(600);
    }
});

it('does not lose any text when splitting', function (): void {
    $text = sentences(300);

    $joined = implode(' ', array_map(fn (ChunkDraft $chunk): string => $chunk->content, chunkText($text, chunker(overlapTokens: 0))));

    expect(preg_replace('/\s+/', ' ', $joined))->toBe($text);
});

it('records the page each chunk starts on', function (): void {
    $chunks = chunker(targetTokens: 40, overlapTokens: 0)->chunk(new ExtractedDocument([
        new ExtractedPage("## Fees\n\nFirst page text about the fees.", 1),
        new ExtractedPage(sentences(4), 2),
        new ExtractedPage("## Hours\n\nThird page.", 3),
    ]));

    expect($chunks[0]->page)->toBe(1)
        ->and(end($chunks)->page)->toBe(3)
        ->and(array_map(fn (ChunkDraft $chunk): ?int => $chunk->page, $chunks))->toContain(2);
});

it('continues a section across a page break', function (): void {
    $chunks = chunker()->chunk(new ExtractedDocument([
        new ExtractedPage("## Fees\n\nStarts on one page.", 1),
        new ExtractedPage('Continues on the next.', 2),
    ]));

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->heading)->toBe('Fees')
        ->and($chunks[0]->page)->toBe(1)
        ->and($chunks[0]->content)->toContain('Continues on the next.');
});

it('numbers chunks in reading order', function (): void {
    $chunks = chunkText("# A\n\nOne.\n\n# B\n\nTwo.\n\n# C\n\nThree.");

    expect(array_map(fn (ChunkDraft $chunk): int => $chunk->position, $chunks))->toBe([0, 1, 2]);
});

it('counts tokens by bytes so non-Latin text is not underestimated', function (): void {
    $counter = new EstimatedTokenCounter;

    expect($counter->count('abcd'))->toBe(1)
        ->and($counter->count('пока'))->toBe(2);
});

it('returns nothing for a document without text', function (): void {
    expect(chunkText("   \n\n  "))->toBe([]);
});
