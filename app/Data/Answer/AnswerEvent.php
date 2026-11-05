<?php

declare(strict_types=1);

namespace App\Data\Answer;

use App\Models\Conversation;
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

    public static function started(Conversation $conversation): self
    {
        return new self(AnswerEventType::Started, ['conversation_id' => $conversation->id]);
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

    /**
     * Renders the event in the Server-Sent Events wire format.
     */
    public function toServerSentEvent(): string
    {
        return sprintf("event: %s\ndata: %s\n\n", $this->type->value, json_encode($this->data, JSON_THROW_ON_ERROR));
    }
}
