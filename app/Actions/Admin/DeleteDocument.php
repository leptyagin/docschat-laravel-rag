<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Document;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

final readonly class DeleteDocument
{
    public function __construct(
        private FilesystemFactory $filesystem,
        #[Config('docschat.ingestion.disk')] private string $disk,
    ) {}

    /**
     * Its chunks go with it through the foreign key, and any job still queued
     * for the document is dropped when it finds the model gone.
     */
    public function handle(Document $document): void
    {
        $document->delete();

        if ($document->file_path !== null) {
            $this->filesystem->disk($this->disk)->delete($document->file_path);
        }
    }
}
