<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Chunking;

use App\Data\Ingestion\ChunkDraft;
use App\Data\Ingestion\ExtractedDocument;
use App\Data\Ingestion\TextBlock;
use App\Data\Ingestion\TextSection;
use Illuminate\Container\Attributes\Config;

/**
 * Splits text by headings first, then by size. Overlap stays inside a section:
 * text under another heading is another topic, so repeating it adds only noise.
 */
final readonly class TextChunker
{
    private const string HEADING_PATTERN = '/^(#{1,6})\s+(.+?)\s*#*$/';

    private const string HEADING_SEPARATOR = ' > ';

    private const string BLOCK_SEPARATOR = "\n\n";

    /**
     * From the coarsest boundary to the finest, as [pattern, glue].
     *
     * @var list<array{non-empty-string, string}>
     */
    private const array BOUNDARIES = [
        ['/\n+/u', "\n"],
        ['/(?<=[.!?])\s+/u', ' '],
        ['/\s+/u', ' '],
    ];

    public function __construct(
        private TokenCounter $tokens,
        #[Config('docschat.chunking.target_tokens')] private int $targetTokens,
        #[Config('docschat.chunking.overlap_tokens')] private int $overlapTokens,
    ) {}

    /**
     * @return list<ChunkDraft>
     */
    public function chunk(ExtractedDocument $document): array
    {
        $drafts = [];

        foreach ($this->sections($document) as $section) {
            foreach ($this->pack($section) as [$content, $page]) {
                $drafts[] = new ChunkDraft(
                    position: count($drafts),
                    heading: $section->heading,
                    page: $page,
                    content: $content,
                    tokenCount: $this->tokens->count($content),
                );
            }
        }

        return $drafts;
    }

    /**
     * @return list<TextSection>
     */
    private function sections(ExtractedDocument $document): array
    {
        $sections = [];
        $headings = [];
        $blocks = [];

        foreach ($document->pages as $page) {
            foreach (preg_split('/\n{2,}/', trim($page->text)) ?: [] as $paragraph) {
                $lines = [];

                foreach (explode("\n", $paragraph) as $line) {
                    if (preg_match(self::HEADING_PATTERN, trim($line), $matches) !== 1) {
                        $lines[] = $line;

                        continue;
                    }

                    $this->addBlock($blocks, $lines, $page->number);
                    $this->closeSection($sections, $headings, $blocks);

                    $level = strlen($matches[1]);
                    $headings = array_filter($headings, fn (int $depth): bool => $depth < $level, ARRAY_FILTER_USE_KEY);
                    $headings[$level] = $matches[2];
                    $lines = [];
                }

                $this->addBlock($blocks, $lines, $page->number);
            }
        }

        $this->closeSection($sections, $headings, $blocks);

        return $sections;
    }

    /**
     * @param  list<TextBlock>  $blocks
     * @param  list<string>  $lines
     */
    private function addBlock(array &$blocks, array $lines, ?int $page): void
    {
        $text = trim(implode("\n", $lines));

        if ($text !== '') {
            $blocks[] = new TextBlock($text, $page);
        }
    }

    /**
     * @param  list<TextSection>  $sections
     * @param  array<int, string>  $headings
     * @param  list<TextBlock>  $blocks
     */
    private function closeSection(array &$sections, array $headings, array &$blocks): void
    {
        if ($blocks === []) {
            return;
        }

        ksort($headings);

        $sections[] = new TextSection(
            heading: $headings === [] ? null : implode(self::HEADING_SEPARATOR, $headings),
            blocks: $blocks,
        );

        $blocks = [];
    }

    /**
     * @return list<array{string, int|null}>
     */
    private function pack(TextSection $section): array
    {
        $fragmentLimit = max(1, $this->targetTokens - $this->overlapTokens - 1);
        $chunks = [];
        $current = '';
        $currentPage = null;

        foreach ($section->blocks as $block) {
            foreach ($this->split($block->text, $fragmentLimit) as $fragment) {
                if ($current === '') {
                    $current = $fragment;
                    $currentPage = $block->page;

                    continue;
                }

                $candidate = $current.self::BLOCK_SEPARATOR.$fragment;

                if ($this->tokens->count($candidate) <= $this->targetTokens) {
                    $current = $candidate;

                    continue;
                }

                $chunks[] = [$current, $currentPage];

                $overlap = $this->overlapFrom($current);
                $current = $overlap === '' ? $fragment : $overlap.self::BLOCK_SEPARATOR.$fragment;
                $currentPage = $block->page;
            }
        }

        if ($current !== '') {
            $chunks[] = [$current, $currentPage];
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function split(string $text, int $limit, int $depth = 0): array
    {
        if ($this->tokens->count($text) <= $limit) {
            return [$text];
        }

        if (! isset(self::BOUNDARIES[$depth])) {
            return mb_str_split($text, $limit);
        }

        [$pattern, $glue] = self::BOUNDARIES[$depth];

        $parts = [];
        $current = '';

        foreach (preg_split($pattern, $text, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
            foreach ($this->split($piece, $limit, $depth + 1) as $fragment) {
                $candidate = $current === '' ? $fragment : $current.$glue.$fragment;

                if ($this->tokens->count($candidate) <= $limit) {
                    $current = $candidate;

                    continue;
                }

                $parts[] = $current;
                $current = $fragment;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * The end of the text within the overlap budget, starting at a sentence
     * boundary whenever the budget contains one.
     */
    private function overlapFrom(string $text): string
    {
        $tail = '';

        foreach (array_reverse(preg_split('/\s+/u', $text, flags: PREG_SPLIT_NO_EMPTY) ?: []) as $word) {
            $candidate = $tail === '' ? $word : $word.' '.$tail;

            if ($this->tokens->count($candidate) > $this->overlapTokens) {
                break;
            }

            $tail = $candidate;
        }

        return preg_match('/[.!?]\s+(\S.*)$/su', $tail, $matches) === 1 ? $matches[1] : $tail;
    }
}
