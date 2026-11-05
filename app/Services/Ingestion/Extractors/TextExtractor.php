<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Extractors;

use App\Data\Ingestion\ExtractedDocument;
use App\Models\Document;

interface TextExtractor
{
    public function extract(Document $document): ExtractedDocument;
}
