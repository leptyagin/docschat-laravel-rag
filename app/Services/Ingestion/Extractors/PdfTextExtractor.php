<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Extractors;

use App\Data\Ingestion\ExtractedDocument;
use App\Data\Ingestion\ExtractedPage;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Models\Document;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Process\Factory as ProcessFactory;
use XMLReader;

/**
 * Reads the layout rather than the plain text of a PDF, because plain output
 * loses what a heading is. A line set clearly larger than the body text of the
 * document is a heading, and the larger sizes rank higher.
 *
 * The layout carries an element for every word, so it is read as a stream:
 * building a DOM of it needed about 1 MB of memory per page.
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
        #[Config('docschat.ingestion.max_pdf_pages')] private int $maxPages,
        #[Config('docschat.ingestion.max_pdf_layout_bytes')] private int $maxLayoutBytes,
    ) {}

    public function extract(Document $document): ExtractedDocument
    {
        $path = $this->filesystem->disk($this->disk)->path((string) $document->file_path);
        $layoutBytes = 0;
        $isTooLarge = false;

        $result = $this->process->newPendingProcess()
            ->timeout($this->timeout)
            ->run(
                ['pdftotext', '-bbox-layout', '-enc', 'UTF-8', '-q', '-l', (string) ($this->maxPages + 1), $path, '-'],
                function (string $type, string $output) use (&$layoutBytes, &$isTooLarge): void {
                    $layoutBytes += strlen($output);

                    // The process calls this once more while it is destroyed, when nothing could catch a second exception.
                    if ($layoutBytes > $this->maxLayoutBytes && ! $isTooLarge) {
                        $isTooLarge = true;

                        throw new UnprocessableSourceException('The PDF has too much text to ingest.');
                    }
                },
            );

        if ($result->failed()) {
            throw new UnprocessableSourceException('The PDF could not be read. It may be damaged or password protected.');
        }

        $pages = $this->parseLayout($result->output());

        if (count($pages) > $this->maxPages) {
            throw new UnprocessableSourceException("The PDF has more than {$this->maxPages} pages.");
        }

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
     * External entities stay off: the reader neither loads the DTD nor uses the
     * network, so a crafted PDF cannot reach out through its text layer.
     *
     * @return array<int, list<Block>> Blocks keyed by page number.
     */
    private function parseLayout(string $layout): array
    {
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $reader = XMLReader::fromString($layout, flags: LIBXML_NONET);
            $pages = [];
            $page = 0;
            $block = [];
            $words = [];
            $size = 0.0;

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    switch ($reader->localName) {
                        case 'page':
                            $pages[++$page] = [];
                            break;
                        case 'line':
                            $words = [];
                            $size = round((float) $reader->getAttribute('yMax') - (float) $reader->getAttribute('yMin'), 1);
                            break;
                        case 'word':
                            $words[] = trim($reader->readString());
                            break;
                    }
                }

                if ($reader->nodeType === XMLReader::END_ELEMENT) {
                    if ($reader->localName === 'line' && implode('', $words) !== '') {
                        $block[] = ['text' => implode(' ', $words), 'size' => $size];
                    }

                    if ($reader->localName === 'block' && $block !== []) {
                        $pages[$page][] = $block;
                        $block = [];
                    }
                }
            }

            if (libxml_get_last_error() !== false) {
                throw new UnprocessableSourceException('The PDF could not be read. It may be damaged or password protected.');
            }

            return $pages;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
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
            $heading = [];
            $headingLevel = null;

            foreach ($block as $line) {
                $level = $this->headingLevelOf($line, $headingLevels);

                if ($level !== $headingLevel && $heading !== []) {
                    $paragraphs[] = str_repeat('#', (int) $headingLevel).' '.implode(' ', $heading);
                    $heading = [];
                }

                if ($level === null) {
                    $body[] = $line['text'];
                } else {
                    if ($body !== []) {
                        $paragraphs[] = implode("\n", $body);
                        $body = [];
                    }

                    $heading[] = $line['text'];
                }

                $headingLevel = $level;
            }

            if ($heading !== []) {
                $paragraphs[] = str_repeat('#', (int) $headingLevel).' '.implode(' ', $heading);
            }

            if ($body !== []) {
                $paragraphs[] = implode("\n", $body);
            }
        }

        return implode("\n\n", $paragraphs);
    }

    /**
     * @param  Line  $line
     * @param  array<string, int>  $headingLevels
     */
    private function headingLevelOf(array $line, array $headingLevels): ?int
    {
        $wordCount = count(preg_split('/\s+/u', $line['text'], flags: PREG_SPLIT_NO_EMPTY) ?: []);

        return $wordCount > self::MAX_HEADING_WORDS ? null : $headingLevels[(string) $line['size']] ?? null;
    }
}
