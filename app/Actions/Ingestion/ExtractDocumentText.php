<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Ai\Contracts\EmbeddingModel;
use App\Data\Ingestion\ExtractedDocument;
use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Models\Document;
use App\Services\Ingestion\Extractors\PdfTextExtractor;
use App\Services\Ingestion\Extractors\PlainTextExtractor;
use App\Services\Ingestion\Extractors\TextExtractor;
use App\Services\Ingestion\Extractors\WebPageExtractor;

final readonly class ExtractDocumentText
{
    public function __construct(
        private PdfTextExtractor $pdf,
        private WebPageExtractor $webPage,
        private PlainTextExtractor $plainText,
        private EmbeddingModel $embeddings,
    ) {}

    /**
     * @return bool Whether the document needs to be indexed again.
     */
    public function handle(Document $document): bool
    {
        $document->update(['status' => DocumentStatus::Processing, 'error' => null]);

        $extraction = $this->extractorFor($document->source_type)->extract($document);

        if ($extraction->isEmpty()) {
            throw new UnprocessableSourceException('No text could be extracted. Scanned documents are not supported.');
        }

        if ($this->isIndexed($document, $extraction)) {
            $document->update(['status' => DocumentStatus::Ready]);

            return false;
        }

        $document->update([
            'extraction' => $extraction->toArray(),
            'title' => $this->titleFor($document, $extraction),
        ]);

        return true;
    }

    /**
     * Vectors from different models cannot be compared, so unchanged text still
     * needs indexing after the embedding model has changed.
     */
    private function isIndexed(Document $document, ExtractedDocument $extraction): bool
    {
        return $extraction->checksum() === $document->checksum
            && $document->embedding_model === $this->embeddings->name();
    }

    private function extractorFor(DocumentSourceType $sourceType): TextExtractor
    {
        return match ($sourceType) {
            DocumentSourceType::Pdf => $this->pdf,
            DocumentSourceType::Url => $this->webPage,
            DocumentSourceType::Text => $this->plainText,
        };
    }

    /**
     * A URL stands in as the title until the page provides a real one.
     */
    private function titleFor(Document $document, ExtractedDocument $extraction): string
    {
        return $document->title === $document->source_url
            ? $extraction->title ?? $document->title
            : $document->title;
    }
}
