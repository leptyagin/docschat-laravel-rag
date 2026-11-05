<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A chunk without an embedding is a draft: it is invisible to retrieval until
 * its vector is written, which is what makes replacing a document atomic.
 */
#[Fillable(['document_id', 'bot_id', 'position', 'heading', 'page', 'content', 'token_count', 'embedding'])]
#[Hidden(['embedding', 'tsv'])]
#[WithoutTimestamps]
class Chunk extends Model
{
    /** @use HasFactory<ChunkFactory> */
    use HasFactory;

    /**
     * The text sent to the embedding model. The heading trail carries context
     * that the body often leaves implicit, such as which hotel a rule is for.
     */
    public function embeddingInput(): string
    {
        return $this->heading === null ? $this->content : $this->heading."\n\n".$this->content;
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<Bot, $this>
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('embedding');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function drafts(Builder $query): void
    {
        $query->whereNull('embedding');
    }

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => AsVector::class,
        ];
    }
}
