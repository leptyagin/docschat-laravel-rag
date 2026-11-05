<?php

declare(strict_types=1);

namespace App\Actions\Answer;

use App\Models\Bot;
use App\Models\Conversation;
use App\Services\Answer\ConversationTokens;
use App\Services\Answer\VisitorHasher;

final readonly class OpenConversation
{
    public function __construct(
        private ConversationTokens $tokens,
        private VisitorHasher $visitors,
    ) {}

    /**
     * Continues the conversation of a token, which the request has already
     * validated against the bot, or starts a new one.
     */
    public function handle(Bot $bot, ?string $token, ?string $ipAddress, ?string $userAgent): Conversation
    {
        $conversation = $token === null
            ? $bot->conversations()->create(['visitor_hash' => $this->visitors->hash($ipAddress, $userAgent)])
            : $bot->conversations()->findOrFail($this->tokens->conversationId($token));

        return $conversation->setRelation('bot', $bot);
    }
}
