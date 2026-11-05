<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Ai\Contracts\EmbeddingModel;
use App\Data\Answer\ModelUsage;
use App\Data\Answer\RetrievalResult;
use App\Data\Answer\RetrievedChunk;
use App\Models\Bot;
use App\Models\Chunk;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\DB;

/**
 * Finds chunks by meaning and by exact wording, then merges both rankings with
 * Reciprocal Rank Fusion. Fusion needs only ranks, so it can combine a cosine
 * distance and a text rank, which share no common scale.
 */
final readonly class HybridRetriever
{
    /**
     * Each ranking is limited before it is numbered. Numbering first would make
     * PostgreSQL read every row, and the HNSW index could not be used.
     */
    private const string RANKING_SQL = <<<'SQL'
        with semantic as (
            select id, distance, row_number() over (order by distance, id) as rank
            from (
                select id, embedding <=> ?::vector as distance
                from chunks
                where bot_id = ? and embedding is not null
                order by embedding <=> ?::vector
                limit ?
            ) as nearest
        ),
        lexical as (
            select id, row_number() over (order by relevance desc, id) as rank
            from (
                select chunks.id, ts_rank_cd(chunks.tsv, search.query) as relevance
                from chunks, websearch_to_tsquery('english', ?) as search(query)
                where chunks.bot_id = ? and chunks.embedding is not null and chunks.tsv @@ search.query
                order by relevance desc, chunks.id
                limit ?
            ) as matches
        )
        select
            coalesce(semantic.id, lexical.id) as id,
            1 - semantic.distance as similarity,
            max(1 - semantic.distance) over () as best_similarity,
            coalesce(1.0 / (? + semantic.rank), 0) + coalesce(1.0 / (? + lexical.rank), 0) as score
        from semantic
        full join lexical on semantic.id = lexical.id
        order by score desc, id
        limit ?
        SQL;

    public function __construct(
        private EmbeddingModel $embeddings,
        #[Config('docschat.retrieval.candidates')] private int $candidates,
        #[Config('docschat.retrieval.keep')] private int $keep,
        #[Config('docschat.retrieval.rrf_k')] private int $rrfK,
    ) {}

    /**
     * A chunk that a re-index replaced between ranking and loading is skipped.
     */
    public function retrieve(Bot $bot, string $query): RetrievalResult
    {
        $embedding = $this->embeddings->embed([$query]);
        $rows = $this->rank($bot, $query, $embedding->vectors[0]);

        $chunks = Chunk::query()
            ->with('document:id,title,source_url')
            ->findMany(array_map(fn (object $row): int => $row->id, $rows), ['id', 'document_id', 'heading', 'page', 'content'])
            ->keyBy('id');

        $retrieved = [];

        foreach ($rows as $row) {
            if ($chunks->has($row->id)) {
                $retrieved[] = new RetrievedChunk(
                    chunk: $chunks[$row->id],
                    similarity: $row->similarity === null ? null : (float) $row->similarity,
                    score: (float) $row->score,
                );
            }
        }

        return new RetrievalResult(
            chunks: $retrieved,
            bestSimilarity: isset($rows[0]->best_similarity) ? (float) $rows[0]->best_similarity : null,
            usage: new ModelUsage($embedding->model, $embedding->promptTokens),
        );
    }

    /**
     * An HNSW scan applies the bot filter after it has collected its neighbours,
     * so with many bots it could come back short. Iterative scanning keeps going
     * until the limit is met.
     *
     * @param  list<float>  $vector
     * @return list<object{id: int, similarity: float|string|null, best_similarity: float|string|null, score: float|string}>
     */
    private function rank(Bot $bot, string $query, array $vector): array
    {
        $encodedVector = json_encode($vector, JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($bot, $query, $encodedVector): array {
            DB::statement("set local hnsw.iterative_scan = 'strict_order'");

            return DB::select(self::RANKING_SQL, [
                $encodedVector, $bot->id, $encodedVector, $this->candidates,
                $query, $bot->id, $this->candidates,
                $this->rrfK, $this->rrfK,
                $this->keep,
            ]);
        });
    }
}
