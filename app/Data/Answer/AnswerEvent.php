<?php

declare(strict_types=1);

namespace App\Data\Answer;

use App\Models\Message;

final readonly class AnswerEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public AnswerEventType $type,
        public array $data,
    ) {}

    public static function started(string $conversationToken): self
    {
        return new self(AnswerEventType::Started, ['conversation_token' => $conversationToken]);
    }

    public static function delta(string $text): self
    {
        return new self(AnswerEventType::Delta, ['text' => $text]);
    }

    /**
     * @param  list<Source>  $sources
     */
    public static function completed(Message $message, array $sources): self
    {
        return new self(AnswerEventType::Completed, [
            'message_id' => $message->id,
            'answered' => $message->answered,
            'sources' => array_map(fn (Source $source): array => $source->toArray(), $sources),
        ]);
    }
}
