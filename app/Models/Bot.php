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
 */
#[Fillable(['name', 'slug', 'public_key', 'greeting', 'fallback_contact', 'theme', 'suggested_questions'])]
class Bot extends Model
{
    /** @use HasFactory<BotFactory> */
    use HasFactory;

    public static function generatePublicKey(): string
    {
        return 'pk_'.Str::random(40);
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
        ];
    }
}
