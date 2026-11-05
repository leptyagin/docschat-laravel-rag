<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Ai\Contracts\EmbeddingModel;
use App\Enums\DocumentStatus;
use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Container\Attributes\Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Embeds the draft chunks of a document and swaps them in for the published
 * ones. All provider calls happen first, so the transaction stays short and a
 * failed call leaves the current version of the document fully searchable.
 */
final readonly class PublishDocumentChunks
{
    private const int WRITE_BATCH_SIZE = 200;

    public function __construct(
        private EmbeddingModel $embeddings,
        #[Config('docschat.ai.embeddings.batch_size')] private int $batchSize,
    ) {}

    public function handle(Document $document): void
    {
        $drafts = $document->chunks()->drafts()->orderBy('position')->get(['id', 'heading', 'content']);

        if ($drafts->isEmpty()) {
            return;
        }

        $vectors = $this->embed($drafts);

        DB::transaction(function () use ($document, $vectors): void {
            $document->chunks()->published()->delete();

            foreach (array_chunk($vectors, self::WRITE_BATCH_SIZE, preserve_keys: true) as $batch) {
                $this->writeVectors($batch);
            }

            $document->update([
                'status' => DocumentStatus::Ready,
                'checksum' => $document->extraction?->checksum(),
                'chunk_count' => count($vectors),
                'extraction' => null,
            ]);
        });
    }

    /**
     * @param  Collection<int, Chunk>  $drafts
     * @return array<int, list<float>> Vectors keyed by chunk id.
     */
    private function embed(Collection $drafts): array
    {
        $vectors = [];

        foreach ($drafts->chunk($this->batchSize) as $batch) {
            $result = $this->embeddings->embed(
                $batch->map(fn (Chunk $chunk): string => $chunk->embeddingInput())->values()->all(),
            );

            $vectors += array_combine($batch->modelKeys(), $result->vectors);
        }

        return $vectors;
    }

    /**
     * One statement per batch instead of one per chunk.
     *
     * @param  array<int, list<float>>  $vectors
     */
    private function writeVectors(array $vectors): void
    {
        $rows = [];

        foreach ($vectors as $id => $vector) {
            $rows[] = ['id' => $id, 'embedding' => json_encode($vector, JSON_THROW_ON_ERROR)];
        }

        DB::update(
            'update chunks set embedding = data.embedding::vector
             from jsonb_to_recordset(?::jsonb) as data(id bigint, embedding text)
             where chunks.id = data.id',
            [json_encode($rows, JSON_THROW_ON_ERROR)],
        );
    }
}
