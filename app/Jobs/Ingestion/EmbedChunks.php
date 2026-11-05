<?php

declare(strict_types=1);

namespace App\Jobs\Ingestion;

use App\Actions\Ingestion\EmbedDocumentChunks;
use App\Jobs\Ingestion\Concerns\HandlesIngestionFailure;
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
#[Timeout(280)]
#[DeleteWhenMissingModels]
final class EmbedChunks implements ShouldQueue
{
    use HandlesIngestionFailure, Queueable;

    public function __construct(public Document $document) {}

    public function handle(EmbedDocumentChunks $embed): void
    {
        if (! $embed->handle($this->document)) {
            self::dispatch($this->document);
        }
    }
}
