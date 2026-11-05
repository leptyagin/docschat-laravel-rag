<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentStatus;
use App\Jobs\Ingestion\ExtractText;
use App\Models\Document;
use Throwable;

final class StartIngestion
{
    /**
     * A document must not be left pending with no job behind it, so a failed
     * dispatch is recorded before the exception continues. On the sync queue the
     * job itself runs here, and then it has already recorded its own outcome.
     */
    public function handle(Document $document): void
    {
        $document->fill(['status' => DocumentStatus::Pending, 'error' => null])->save();

        try {
            ExtractText::dispatch($document);
        } catch (Throwable $exception) {
            if ($document->refresh()->status === DocumentStatus::Pending) {
                $document->markFailed('The document could not be queued. Please try again.');
            }

            throw $exception;
        }
    }
}
