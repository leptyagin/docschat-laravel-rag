<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentSourceType;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Models\Bot;
use App\Models\Document;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\File;
use Illuminate\Support\Str;

/**
 * The file name identifies a document within a bot, so uploading a file under
 * the same name replaces the earlier version instead of adding a duplicate.
 */
final readonly class IngestFile
{
    public function __construct(
        private StartIngestion $startIngestion,
        private FilesystemFactory $filesystem,
        #[Config('docschat.ingestion.disk')] private string $disk,
    ) {}

    public function handle(Bot $bot, string $path, ?string $title = null): Document
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new UnprocessableSourceException("The file [{$path}] cannot be read.");
        }

        $file = new File($path);
        $sourceType = $this->sourceTypeOf($file);
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $directory = "documents/{$bot->id}";
        $storedName = Str::slug($baseName).'.'.strtolower($file->getExtension());

        $document = Document::query()->firstOrNew([
            'bot_id' => $bot->id,
            'file_path' => "{$directory}/{$storedName}",
        ]);

        if ($document->exists && $document->status->isInFlight()) {
            return $document;
        }

        $this->filesystem->disk($this->disk)->putFileAs($directory, $file, $storedName);

        $document->fill([
            'source_type' => $sourceType,
            'title' => $title ?? Str::headline($baseName),
        ]);

        $this->startIngestion->handle($document);

        return $document;
    }

    private function sourceTypeOf(File $file): DocumentSourceType
    {
        $extension = strtolower($file->getExtension());

        return match (true) {
            $extension === 'pdf' && $file->getMimeType() === 'application/pdf' => DocumentSourceType::Pdf,
            in_array($extension, ['md', 'txt'], true) => DocumentSourceType::Text,
            default => throw new UnprocessableSourceException('Only PDF, Markdown and plain text files are supported.'),
        };
    }
}
