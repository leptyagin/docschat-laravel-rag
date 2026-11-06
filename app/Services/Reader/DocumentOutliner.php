<?php

declare(strict_types=1);

namespace App\Services\Reader;

use App\Data\Reader\ReaderDocument;
use App\Data\Reader\ReaderPassage;
use App\Data\Reader\ReaderSection;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Rebuilds a readable document from its published chunks, which are the same
 * text that answers cite. A chunk keeps its heading trail, such as
 * "Parking > Harbor View", and the trails give back the document's structure.
 */
final readonly class DocumentOutliner
{
    private const string HEADING_SEPARATOR = ' > ';

    private const string BLOCK_SEPARATOR = "\n\n";

    private const int TOKENS_READ_PER_MINUTE = 260;

    public function __construct(private PassageRenderer $renderer) {}

    public function outline(Bot $bot, Document $document): ReaderDocument
    {
        $chunks = $document->chunks()
            ->published()
            ->orderBy('position')
            ->get(['position', 'heading', 'page', 'content', 'token_count']);

        $pageCount = $chunks->max('page');

        return new ReaderDocument(
            id: $document->id,
            title: $document->title,
            typeLabel: $document->source_type->label(),
            updatedAt: $document->updated_at,
            readingMinutes: max(1, (int) ceil($chunks->sum('token_count') / self::TOKENS_READ_PER_MINUTE)),
            pageCount: is_int($pageCount) ? $pageCount : null,
            passageCount: $chunks->count(),
            originalUrl: route('widget.documents.show', [$bot->public_key, $document->id]),
            sections: $this->sections($chunks),
        );
    }

    /**
     * @param  Collection<int, Chunk>  $chunks
     * @return list<ReaderSection>
     */
    private function sections(Collection $chunks): array
    {
        $sections = [];
        $anchors = [];
        $openTrail = [];

        foreach ($this->runs($chunks) as ['trail' => $trail, 'passages' => $passages]) {
            array_push($sections, ...$this->sectionsFor($trail, $openTrail, $passages, $anchors));
            $openTrail = $trail;
        }

        return $sections;
    }

    /**
     * Groups neighbouring chunks that sit under the same headings.
     *
     * @param  Collection<int, Chunk>  $chunks
     * @return list<array{trail: list<string>, passages: list<ReaderPassage>}>
     */
    private function runs(Collection $chunks): array
    {
        $root = $this->sharedRoot($chunks);
        $runs = [];
        $currentTrail = null;
        $previousContent = null;
        $lastPage = null;

        foreach ($chunks as $chunk) {
            $trail = $this->trail($chunk, $root);

            if ($trail !== $currentTrail) {
                $runs[] = ['trail' => $trail, 'passages' => []];
                $currentTrail = $trail;
                $previousContent = null;
            }

            $runs[array_key_last($runs)]['passages'][] = new ReaderPassage(
                position: $chunk->position,
                startsPage: $chunk->page !== $lastPage ? $chunk->page : null,
                html: $this->renderer->toHtml($this->withoutOverlap($chunk->content, $previousContent)),
            );

            $previousContent = $chunk->content;
            $lastPage = $chunk->page ?? $lastPage;
        }

        return $runs;
    }

    /**
     * The headings newly opened by a trail. A parent that has no text of its own
     * never appears as a chunk, so it is created here, ahead of its children.
     *
     * @param  list<string>  $trail
     * @param  list<string>  $openTrail
     * @param  list<ReaderPassage>  $passages
     * @param  array<string, int>  $anchors
     * @return list<ReaderSection>
     */
    private function sectionsFor(array $trail, array $openTrail, array $passages, array &$anchors): array
    {
        if ($trail === []) {
            return [new ReaderSection('introduction', null, 1, $passages)];
        }

        $shared = 0;

        while (isset($trail[$shared], $openTrail[$shared]) && $trail[$shared] === $openTrail[$shared]) {
            $shared++;
        }

        $last = count($trail) - 1;
        $sections = [];

        for ($depth = min($shared, $last); $depth <= $last; $depth++) {
            $sections[] = new ReaderSection(
                anchor: $this->uniqueAnchor($trail[$depth], $anchors),
                title: $trail[$depth],
                level: $depth + 1,
                passages: $depth === $last ? $passages : [],
            );
        }

        return $sections;
    }

    /**
     * The title of a document is usually its only top heading. It is already the
     * page title, so it is left out of every trail.
     *
     * @param  Collection<int, Chunk>  $chunks
     */
    private function sharedRoot(Collection $chunks): ?string
    {
        $roots = $chunks
            ->map(fn (Chunk $chunk): ?string => $chunk->heading === null ? null : explode(self::HEADING_SEPARATOR, $chunk->heading)[0])
            ->unique();

        return $roots->count() === 1 ? $roots->first() : null;
    }

    /**
     * @return list<string>
     */
    private function trail(Chunk $chunk, ?string $root): array
    {
        if ($chunk->heading === null) {
            return [];
        }

        $trail = explode(self::HEADING_SEPARATOR, $chunk->heading);

        return $trail[0] === $root ? array_slice($trail, 1) : $trail;
    }

    /**
     * A chunk split from a long section opens with the tail of the one before
     * it, as its own block, so that a search hit keeps its context. A reader
     * must see that text once.
     */
    private function withoutOverlap(string $content, ?string $previous): string
    {
        if ($previous === null || ! str_contains($content, self::BLOCK_SEPARATOR)) {
            return $content;
        }

        [$opening, $rest] = explode(self::BLOCK_SEPARATOR, $content, 2);

        return str_ends_with(Str::squish($previous), Str::squish($opening)) ? $rest : $content;
    }

    /**
     * @param  array<string, int>  $anchors
     */
    private function uniqueAnchor(string $title, array &$anchors): string
    {
        $slug = Str::slug($title) ?: 'section';
        $anchors[$slug] = ($anchors[$slug] ?? 0) + 1;

        return $anchors[$slug] === 1 ? $slug : "{$slug}-{$anchors[$slug]}";
    }
}
