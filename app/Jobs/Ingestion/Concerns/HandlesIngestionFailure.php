<?php

declare(strict_types=1);

namespace App\Jobs\Ingestion\Concerns;

use App\Exceptions\Ingestion\UnprocessableSourceException;
use Illuminate\Queue\Middleware\FailOnException;
use Throwable;

trait HandlesIngestionFailure
{
    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new FailOnException([UnprocessableSourceException::class])];
    }

    /**
     * Only messages written for the owner reach the document. Anything else may
     * contain SQL or internal hosts, and is left to the exception log.
     */
    public function failed(?Throwable $exception): void
    {
        $this->document->markFailed(
            $exception instanceof UnprocessableSourceException
                ? $exception->getMessage()
                : 'Processing failed after several attempts. Please try again later.',
        );
    }
}
