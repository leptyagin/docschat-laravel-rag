<?php

declare(strict_types=1);

namespace App\Actions\Answer;

use App\Models\Bot;
use App\Models\Conversation;

final class OpenConversation
{
    /**
     * Continues the given conversation or starts a new one. The identifier has
     * already been checked against the bot and the visitor.
     */
    public function handle(Bot $bot, string $visitorHash, ?int $conversationId): Conversation
    {
        $conversation = $conversationId === null
            ? $bot->conversations()->create(['visitor_hash' => $visitorHash])
            : $bot->conversations()->findOrFail($conversationId);

        return $conversation->setRelation('bot', $bot);
    }
}
