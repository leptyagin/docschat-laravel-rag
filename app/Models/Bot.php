<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property array{color: string, position: string} $theme
 * @property list<string> $suggested_questions
 * @property list<string> $embed_origins Sites that may embed the chat. Empty means any site.
 * @property bool $documents_public Whether visitors may open whole documents, or only read answers.
 */
#[Fillable(['name', 'slug', 'public_key', 'greeting', 'fallback_contact', 'theme', 'suggested_questions', 'embed_origins', 'documents_public'])]
class Bot extends Model
{
    /** @use HasFactory<BotFactory> */
    use HasFactory;

    public static function generatePublicKey(): string
    {
        return 'pk_'.Str::random(40);
    }

    /**
     * Whether a visitor may read a document as a whole. A file that was never
     * indexed, such as one that failed, was not published yet.
     */
    public function publishes(Document $document): bool
    {
        return $this->documents_public && $document->isReadable();
    }

    /**
     * The value of the frame-ancestors directive for the chat page.
     */
    public function frameAncestors(): string
    {
        return $this->embed_origins === [] ? '*' : implode(' ', $this->embed_origins);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<Chunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'suggested_questions' => 'array',
            'embed_origins' => 'array',
            'documents_public' => 'boolean',
        ];
    }
}
