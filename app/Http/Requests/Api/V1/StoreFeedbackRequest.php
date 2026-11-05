<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\MessageFeedback;
use App\Enums\MessageRole;
use App\Models\Message;
use App\Services\Answer\ConversationTokens;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFeedbackRequest extends FormRequest
{
    /**
     * Only the visitor who holds the conversation may rate its answers.
     */
    public function authorize(ConversationTokens $tokens): bool
    {
        /** @var Message $message */
        $message = $this->route('message');

        return $message->role === MessageRole::Assistant
            && $tokens->conversationId($this->string('conversation_token')->toString()) === $message->conversation_id;
    }

    /**
     * @return array<string, list<ValidationRule|string|object>>
     */
    public function rules(): array
    {
        return [
            'feedback' => ['required', Rule::enum(MessageFeedback::class)],
            'conversation_token' => ['required', 'string'],
        ];
    }

    public function feedback(): MessageFeedback
    {
        return $this->enum('feedback', MessageFeedback::class) ?? MessageFeedback::Up;
    }
}
