<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Bot;
use App\Services\Answer\ConversationTokens;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A forged token and a token of another bot fail alike, so nothing can be probed.
 */
final readonly class ConversationTokenOfBot implements ValidationRule
{
    public function __construct(
        private Bot $bot,
        private ConversationTokens $tokens,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $conversationId = is_string($value) ? $this->tokens->conversationId($value) : null;

        if ($conversationId === null || ! $this->bot->conversations()->whereKey($conversationId)->exists()) {
            $fail('This conversation cannot be continued.');
        }
    }
}
