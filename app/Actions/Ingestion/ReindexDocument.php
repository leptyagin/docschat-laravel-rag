<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Models\Document;

/**
 * Indexes a document again even if its text is unchanged, for example after the
 * chunk size has been tuned.
 */
final readonly class ReindexDocument
{
    public function __construct(private StartIngestion $startIngestion) {}

    public function handle(Document $document): void
    {
        if ($document->isBeingProcessed()) {
            return;
        }

        $document->checksum = null;

        $this->startIngestion->handle($document);
    }
}
