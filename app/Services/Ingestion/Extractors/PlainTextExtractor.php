<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Extractors;

use App\Data\Ingestion\ExtractedDocument;
use App\Data\Ingestion\ExtractedPage;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Models\Document;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

final readonly class PlainTextExtractor implements TextExtractor
{
    public function __construct(
        private FilesystemFactory $filesystem,
        #[Config('docschat.ingestion.disk')] private string $disk,
    ) {}

    public function extract(Document $document): ExtractedDocument
    {
        $contents = $this->filesystem->disk($this->disk)->get((string) $document->file_path);

        if ($contents === null) {
            throw new UnprocessableSourceException('The uploaded file is missing.');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw new UnprocessableSourceException('The file is not valid UTF-8 text.');
        }

        $text = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $contents);

        return new ExtractedDocument([new ExtractedPage(preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text)]);
    }
}
