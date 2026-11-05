<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentStatus;
use App\Jobs\Ingestion\ExtractText;
use App\Models\Document;

final class StartIngestion
{
    public function handle(Document $document): void
    {
        $document->fill(['status' => DocumentStatus::Pending, 'error' => null])->save();

        ExtractText::dispatch($document);
    }
}
