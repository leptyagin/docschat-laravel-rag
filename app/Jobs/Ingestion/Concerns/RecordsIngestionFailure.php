<?php

declare(strict_types=1);

namespace App\Jobs\Ingestion\Concerns;

use App\Exceptions\Ingestion\UnprocessableSourceException;
use Illuminate\Queue\Middleware\FailOnException;
use Illuminate\Support\Str;
use Throwable;

trait RecordsIngestionFailure
{
    private const int MAX_ERROR_LENGTH = 1000;

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new FailOnException([UnprocessableSourceException::class])];
    }

    public function failed(?Throwable $exception): void
    {
        $this->document->markFailed(
            Str::limit($exception?->getMessage() ?? 'Ingestion failed.', self::MAX_ERROR_LENGTH),
        );
    }
}
