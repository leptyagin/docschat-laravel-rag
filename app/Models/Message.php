<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageFeedback;
use App\Enums\MessageRole;
use App\Enums\RefusalReason;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property MessageRole $role
 * @property MessageFeedback|null $feedback
 * @property RefusalReason|null $refusal_reason
 * @property list<int>|null $cited_chunk_ids
 * @property list<array{number: int, title: string, page: int|null, url: string|null, excerpt: string}>|null $sources
 */
#[Fillable([
    'conversation_id',
    'role',
    'content',
    'rewritten_query',
    'cited_chunk_ids',
    'sources',
    'answered',
    'refusal_reason',
    'model',
    'prompt_tokens',
    'completion_tokens',
    'cost_usd',
    'latency_ms',
    'feedback',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'feedback' => MessageFeedback::class,
            'refusal_reason' => RefusalReason::class,
            'cited_chunk_ids' => 'array',
            'sources' => 'array',
            'answered' => 'boolean',
            'cost_usd' => 'decimal:6',
        ];
    }
}
