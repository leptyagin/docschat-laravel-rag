<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\DocumentSourceType;
use App\Models\Bot;
use App\Models\Document;
use App\Services\Ingestion\Web\PublicUrlParser;

final readonly class IngestUrl
{
    public function __construct(
        private StartIngestion $startIngestion,
        private PublicUrlParser $parser,
    ) {}

    public function handle(Bot $bot, string $url, ?string $title = null): Document
    {
        $normalizedUrl = $this->parser->parse($url)->withFragment(null)->toAsciiString();

        $document = Document::query()->firstOrNew([
            'bot_id' => $bot->id,
            'source_url' => $normalizedUrl,
        ]);

        if ($document->isBeingProcessed()) {
            return $document;
        }

        $document->fill([
            'source_type' => DocumentSourceType::Url,
            'title' => $title ?? $document->title ?? $normalizedUrl,
        ]);

        $this->startIngestion->handle($document);

        return $document;
    }
}
