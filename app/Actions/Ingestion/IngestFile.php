<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Models\Bot;
use App\Models\Document;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Support\Str;

/**
 * The original file name identifies a document within a bot, so uploading a file
 * under the same name replaces the earlier version instead of adding a duplicate.
 */
final readonly class IngestFile
{
    private const int MAX_SLUG_LENGTH = 100;

    public function __construct(
        private StartIngestion $startIngestion,
        private FilesystemFactory $filesystem,
        #[Config('docschat.ingestion.disk')] private string $disk,
        #[Config('docschat.ingestion.max_file_bytes')] private int $maxFileBytes,
    ) {}

    /**
     * @param  string|null  $originalName  The name of the file as the owner knows it, for files in a temporary location.
     */
    public function handle(Bot $bot, string $path, ?string $title = null, ?string $originalName = null): Document
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new UnprocessableSourceException('The file cannot be read.');
        }

        $file = new File($path);
        $name = $originalName ?? basename($path);
        $sourceType = $this->sourceTypeOf($file, $name);

        if ((int) $file->getSize() > $this->maxFileBytes) {
            throw new UnprocessableSourceException('The file is too large to ingest.');
        }

        $baseName = pathinfo($name, PATHINFO_FILENAME);
        $directory = "documents/{$bot->id}";
        $storedName = $this->storedName($name);
        $storage = $this->filesystem->disk($this->disk);

        $document = Document::query()->firstOrNew([
            'bot_id' => $bot->id,
            'file_path' => "{$directory}/{$storedName}",
        ]);

        if ($document->isBeingProcessed() || $this->isUnchanged($document, $storage, $file)) {
            return $document;
        }

        $storage->putFileAs($directory, $file, $storedName);

        $document->fill([
            'source_type' => $sourceType,
            'title' => $title ?? Str::headline($baseName),
        ]);

        $this->startIngestion->handle($document);

        return $document;
    }

    /**
     * The hash keeps names apart that slug alike, or that have no Latin letters
     * and would slug to nothing at all.
     */
    private function storedName(string $name): string
    {
        $slug = Str::limit(Str::slug(pathinfo($name, PATHINFO_FILENAME)), self::MAX_SLUG_LENGTH, '');
        $fingerprint = substr(hash('sha256', $name), 0, 8);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return ltrim("{$slug}-{$fingerprint}", '-').'.'.$extension;
    }

    private function isUnchanged(Document $document, Filesystem $storage, File $file): bool
    {
        return $document->exists
            && $document->status === DocumentStatus::Ready
            && $storage->exists((string) $document->file_path)
            && hash('sha256', (string) $storage->get((string) $document->file_path)) === hash_file('sha256', $file->getPathname());
    }

    private function sourceTypeOf(File $file, string $name): DocumentSourceType
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match (true) {
            $extension === 'pdf' && $file->getMimeType() === 'application/pdf' => DocumentSourceType::Pdf,
            in_array($extension, ['md', 'txt'], true) => DocumentSourceType::Text,
            default => throw new UnprocessableSourceException('Only PDF, Markdown and plain text files are supported.'),
        };
    }
}
