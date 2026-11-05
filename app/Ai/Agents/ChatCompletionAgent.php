<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Data\ChatMessage;
use App\Ai\Data\ChatRequest;
use App\Enums\MessageRole;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole as SdkMessageRole;
use Laravel\Ai\Promptable;

/**
 * Adapts a provider-neutral ChatRequest to the SDK's agent contract.
 */
final class ChatCompletionAgent implements Agent, Conversational
{
    use Promptable;

    public function __construct(private readonly ChatRequest $request) {}

    public function instructions(): string
    {
        return $this->request->systemPrompt;
    }

    /**
     * @return list<Message>
     */
    public function messages(): iterable
    {
        return array_map(
            fn (ChatMessage $message): Message => new Message(
                match ($message->role) {
                    MessageRole::User => SdkMessageRole::User,
                    MessageRole::Assistant => SdkMessageRole::Assistant,
                },
                $message->content,
            ),
            $this->request->history,
        );
    }

    public function maxTokens(): int
    {
        return $this->request->maxTokens;
    }
}
