<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Data\Ingestion\ChunkDraft;
use App\Data\Ingestion\ExtractedDocument;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Models\Chunk;
use App\Models\Document;
use App\Services\Ingestion\Chunking\TextChunker;
use Illuminate\Support\Facades\DB;

final readonly class ChunkDocument
{
    private const int INSERT_BATCH_SIZE = 500;

    public function __construct(private TextChunker $chunker) {}

    public function handle(Document $document): void
    {
        $drafts = $document->extraction === null
            ? []
            : $this->chunker->chunk(ExtractedDocument::fromArray($document->extraction));

        if ($drafts === []) {
            throw new UnprocessableSourceException('The document has no text to index.');
        }

        DB::transaction(function () use ($document, $drafts): void {
            Document::query()->lockForUpdate()->findOrFail($document->id, ['id']);

            $document->chunks()->drafts()->delete();

            foreach (array_chunk($drafts, self::INSERT_BATCH_SIZE) as $batch) {
                Chunk::query()->insert(array_map(
                    fn (ChunkDraft $draft): array => [
                        'document_id' => $document->id,
                        'bot_id' => $document->bot_id,
                        'position' => $draft->position,
                        'heading' => $draft->heading,
                        'page' => $draft->page,
                        'content' => $draft->content,
                        'token_count' => $draft->tokenCount,
                    ],
                    $batch,
                ));
            }
        });
    }
}
