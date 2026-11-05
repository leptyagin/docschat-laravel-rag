<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Ai\Contracts\EmbeddingModel;
use App\Data\Ingestion\ExtractedDocument;
use App\Enums\DocumentStatus;
use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Container\Attributes\Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Embeds draft chunks a bounded number of batches at a time, then swaps them in
 * for the published ones. Vectors are saved as they arrive, so a retry resumes
 * instead of paying again, and a document of any size fits within a job timeout.
 */
final readonly class EmbedDocumentChunks
{
    private const int BATCHES_PER_RUN = 4;

    public function __construct(
        private EmbeddingModel $embeddings,
        #[Config('docschat.ai.embeddings.batch_size')] private int $batchSize,
    ) {}

    /**
     * @return bool Whether the document is finished. If not, run this again.
     */
    public function handle(Document $document): bool
    {
        $pending = $document->chunks()
            ->pendingEmbedding()
            ->orderBy('position')
            ->limit($this->batchSize * self::BATCHES_PER_RUN)
            ->get(['id', 'heading', 'content']);

        foreach ($pending->chunk($this->batchSize) as $batch) {
            $this->embed($batch);
        }

        if ($document->chunks()->pendingEmbedding()->exists()) {
            return false;
        }

        $this->publish($document);

        return true;
    }

    /**
     * @param  Collection<int, Chunk>  $batch
     */
    private function embed(Collection $batch): void
    {
        $vectors = $this->embeddings->embed(
            $batch->map(fn (Chunk $chunk): string => $chunk->embeddingInput())->values()->all(),
        )->vectors;

        $rows = [];

        foreach (array_values($batch->modelKeys()) as $index => $id) {
            $rows[] = ['id' => $id, 'embedding' => json_encode($vectors[$index], JSON_THROW_ON_ERROR)];
        }

        DB::update(
            'update chunks set draft_embedding = data.embedding::vector
             from jsonb_to_recordset(?::jsonb) as data(id bigint, embedding text)
             where chunks.id = data.id',
            [json_encode($rows, JSON_THROW_ON_ERROR)],
        );
    }

    /**
     * Duplicate jobs can reach this point together. The row lock lets the first
     * one publish, and the others then find no drafts left and change nothing.
     */
    private function publish(Document $document): void
    {
        DB::transaction(function () use ($document): void {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            $draftCount = $locked->chunks()->drafts()->count();

            if ($draftCount === 0) {
                return;
            }

            $locked->chunks()->published()->delete();

            DB::update(
                'update chunks set embedding = draft_embedding, draft_embedding = null where document_id = ?',
                [$locked->id],
            );

            $locked->update([
                'status' => DocumentStatus::Ready,
                'checksum' => $locked->extraction === null ? null : ExtractedDocument::fromArray($locked->extraction)->checksum(),
                'embedding_model' => $this->embeddings->name(),
                'chunk_count' => $draftCount,
                'extraction' => null,
            ]);
        });
    }
}
