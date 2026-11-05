<?php

declare(strict_types=1);

namespace App\Exceptions\Ingestion;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * The source can never be ingested as it stands, so retrying is pointless. This
 * is a problem with the input, not the application, so it is not logged as one.
 * The message is shown to the owner of the document.
 */
final class UnprocessableSourceException extends RuntimeException implements ShouldntReport {}
