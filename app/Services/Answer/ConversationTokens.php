<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Models\Conversation;
use Illuminate\Container\Attributes\Config;

/**
 * A signed reference that lets a visitor continue a conversation. It does not
 * depend on the visitor's IP address, which changes as a phone moves between
 * networks, and sequential conversation ids cannot be guessed from it.
 */
final readonly class ConversationTokens
{
    public function __construct(
        #[Config('app.key')] private string $key,
    ) {}

    public function issue(Conversation $conversation): string
    {
        return $conversation->id.'.'.$this->signature($conversation->id);
    }

    public function conversationId(string $token): ?int
    {
        [$id, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if (! ctype_digit($id) || strlen($id) > 18 || ! hash_equals($this->signature((int) $id), $signature)) {
            return null;
        }

        return (int) $id;
    }

    private function signature(int $conversationId): string
    {
        return hash_hmac('sha256', "conversation:{$conversationId}", $this->key);
    }
}
