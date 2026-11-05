<?php

declare(strict_types=1);

namespace App\Jobs\Ingestion;

use App\Actions\Ingestion\ExtractDocumentText;
use App\Jobs\Ingestion\Concerns\RecordsIngestionFailure;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Queue('ingestion')]
#[Tries(4)]
#[Backoff(5, 30, 120)]
#[Timeout(120)]
#[DeleteWhenMissingModels]
final class ExtractText implements ShouldQueue
{
    use Queueable, RecordsIngestionFailure;

    public function __construct(public Document $document) {}

    public function handle(ExtractDocumentText $extract): void
    {
        if ($extract->handle($this->document)) {
            ChunkText::dispatch($this->document);
        }
    }
}
