<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property DocumentSourceType $source_type
 * @property DocumentStatus $status
 * @property array{title: string|null, pages: list<array{text: string, number: int|null}>}|null $extraction
 */
#[Fillable([
    'bot_id',
    'source_type',
    'title',
    'source_url',
    'file_path',
    'checksum',
    'embedding_model',
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

    private const int MAX_TITLE_LENGTH = 255;

    /**
     * Longer than the worst case of all pipeline retries. Past this, the jobs of
     * a document were lost, so it must not block another attempt.
     */
    private const int STALE_AFTER_MINUTES = 60;

    public function isBeingProcessed(): bool
    {
        return $this->exists
            && $this->status->isInFlight()
            && $this->updated_at?->isAfter(now()->subMinutes(self::STALE_AFTER_MINUTES)) === true;
    }

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
     * @return Attribute<string, string>
     */
    protected function title(): Attribute
    {
        return Attribute::set(fn (string $title): string => Str::limit($title, self::MAX_TITLE_LENGTH - 3));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => DocumentSourceType::class,
            'status' => DocumentStatus::class,
            'extraction' => 'array',
        ];
    }
}
