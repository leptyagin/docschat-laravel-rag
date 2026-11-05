<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\Ingestion\ExtractedDocument;
use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property DocumentSourceType $source_type
 * @property DocumentStatus $status
 * @property ExtractedDocument|null $extraction
 */
#[Fillable([
    'bot_id',
    'source_type',
    'title',
    'source_url',
    'file_path',
    'checksum',
    'status',
    'error',
    'chunk_count',
    'extraction',
])]
#[Hidden(['extraction'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * Published chunks stay searchable, so a failed re-index never takes a
     * working document offline. Only the unfinished work is discarded.
     */
    public function markFailed(string $error): void
    {
        DB::transaction(function () use ($error): void {
            $this->chunks()->drafts()->delete();

            $this->update([
                'status' => DocumentStatus::Failed,
                'error' => $error,
                'extraction' => null,
            ]);
        });
    }

    /**
     * @return BelongsTo<Bot, $this>
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * @return HasMany<Chunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => DocumentSourceType::class,
            'status' => DocumentStatus::class,
            'extraction' => ExtractedDocument::class,
        ];
    }
}
