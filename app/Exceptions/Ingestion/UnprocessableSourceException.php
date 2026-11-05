<?php

declare(strict_types=1);

namespace App\Exceptions\Ingestion;

use RuntimeException;

/**
 * The source can never be ingested as it stands, so retrying is pointless.
 * The message is shown to the owner of the document.
 */
final class UnprocessableSourceException extends RuntimeException {}
