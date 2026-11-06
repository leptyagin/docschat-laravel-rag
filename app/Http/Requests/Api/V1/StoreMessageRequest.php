<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Bot;
use App\Rules\ConversationTokenOfBot;
use App\Services\Answer\ConversationTokens;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(
        ConversationTokens $tokens,
        #[Config('docschat.answers.max_question_length')] int $maxQuestionLength,
    ): array {
        /** @var Bot $bot */
        $bot = $this->route('bot');

        return [
            'question' => ['required', 'string', 'encoding:UTF-8', "max:{$maxQuestionLength}", 'not_regex:/\x00/'],
            'conversation_token' => ['nullable', 'string', new ConversationTokenOfBot($bot, $tokens)],
        ];
    }

    public function question(): string
    {
        return $this->string('question')->toString();
    }

    public function conversationToken(): ?string
    {
        return $this->filled('conversation_token') ? $this->string('conversation_token')->toString() : null;
    }
}
