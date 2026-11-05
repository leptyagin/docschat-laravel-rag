<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Extractors;

use App\Data\Ingestion\ExtractedDocument;
use App\Data\Ingestion\ExtractedPage;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Models\Document;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Process\Factory as ProcessFactory;

/**
 * Reads the layout rather than the plain text of a PDF, because plain output
 * loses what a heading is. A line set clearly larger than the body text of the
 * document is a heading, and the larger sizes rank higher.
 *
 * @phpstan-type Line array{text: string, size: float}
 * @phpstan-type Block list<Line>
 */
final readonly class PdfTextExtractor implements TextExtractor
{
    private const float HEADING_SIZE_RATIO = 1.1;

    private const int MAX_HEADING_WORDS = 15;

    private const int MAX_HEADING_LEVEL = 6;

    public function __construct(
        private ProcessFactory $process,
        private FilesystemFactory $filesystem,
        #[Config('docschat.ingestion.disk')] private string $disk,
        #[Config('docschat.ingestion.pdf_timeout')] private int $timeout,
    ) {}

    public function extract(Document $document): ExtractedDocument
    {
        $path = $this->filesystem->disk($this->disk)->path((string) $document->file_path);

        $result = $this->process->newPendingProcess()
            ->timeout($this->timeout)
            ->run(['pdftotext', '-bbox-layout', '-enc', 'UTF-8', '-q', $path, '-']);

        if ($result->failed()) {
            throw new UnprocessableSourceException('The PDF could not be read. It may be damaged or password protected.');
        }

        $pages = $this->parseLayout($result->output());
        $headingLevels = $this->headingLevels($pages);
        $extracted = [];

        foreach ($pages as $number => $blocks) {
            if ($blocks !== []) {
                $extracted[] = new ExtractedPage($this->renderPage($blocks, $headingLevels), $number);
            }
        }

        return new ExtractedDocument($extracted);
    }

    /**
     * The HTML parser tolerates anything a damaged PDF may produce and, unlike
     * an XML parser, has no external entities to worry about.
     *
     * @return array<int, list<Block>> Blocks keyed by page number.
     */
    private function parseLayout(string $layout): array
    {
        $pages = [];

        foreach (HTMLDocument::createFromString($layout, LIBXML_NOERROR)->querySelectorAll('page') as $index => $page) {
            $blocks = [];

            foreach ($page->querySelectorAll('block') as $block) {
                $lines = $this->parseLines($block);

                if ($lines !== []) {
                    $blocks[] = $lines;
                }
            }

            $pages[$index + 1] = $blocks;
        }

        return $pages;
    }

    /**
     * @return Block
     */
    private function parseLines(Element $block): array
    {
        $lines = [];

        foreach ($block->querySelectorAll('line') as $line) {
            $words = [];

            foreach ($line->querySelectorAll('word') as $word) {
                $words[] = trim((string) $word->textContent);
            }

            $text = trim(implode(' ', $words));

            if ($text !== '') {
                $lines[] = [
                    'text' => $text,
                    'size' => round((float) $line->getAttribute('ymax') - (float) $line->getAttribute('ymin'), 1),
                ];
            }
        }

        return $lines;
    }

    /**
     * Maps each heading size to a level. The body size is the one that carries
     * the most text, which holds for any document that is more than a title.
     *
     * @param  array<int, list<Block>>  $pages
     * @return array<string, int>
     */
    private function headingLevels(array $pages): array
    {
        $charactersBySize = [];

        foreach ($pages as $blocks) {
            foreach ($blocks as $block) {
                foreach ($block as $line) {
                    $size = (string) $line['size'];
                    $charactersBySize[$size] = ($charactersBySize[$size] ?? 0) + mb_strlen($line['text']);
                }
            }
        }

        if ($charactersBySize === []) {
            return [];
        }

        arsort($charactersBySize);

        $bodySize = (float) array_key_first($charactersBySize);

        $headingSizes = array_filter(
            array_map(floatval(...), array_keys($charactersBySize)),
            fn (float $size): bool => $size >= $bodySize * self::HEADING_SIZE_RATIO,
        );

        rsort($headingSizes);

        $levels = [];

        foreach ($headingSizes as $rank => $size) {
            $levels[(string) $size] = min($rank + 1, self::MAX_HEADING_LEVEL);
        }

        return $levels;
    }

    /**
     * @param  list<Block>  $blocks
     * @param  array<string, int>  $headingLevels
     */
    private function renderPage(array $blocks, array $headingLevels): string
    {
        $paragraphs = [];

        foreach ($blocks as $block) {
            $body = [];

            foreach ($block as $line) {
                $level = $headingLevels[(string) $line['size']] ?? null;

                if ($level === null || str_word_count($line['text']) > self::MAX_HEADING_WORDS) {
                    $body[] = $line['text'];

                    continue;
                }

                if ($body !== []) {
                    $paragraphs[] = implode("\n", $body);
                    $body = [];
                }

                $paragraphs[] = str_repeat('#', $level).' '.$line['text'];
            }

            if ($body !== []) {
                $paragraphs[] = implode("\n", $body);
            }
        }

        return implode("\n\n", $paragraphs);
    }
}
