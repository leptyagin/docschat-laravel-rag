<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Bot;
use App\Services\Answer\VisitorHasher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A conversation of another bot or visitor fails exactly like one that does
     * not exist, so identifiers cannot be probed.
     *
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:'.config()->integer('docschat.answers.max_question_length')],
            'conversation_id' => [
                'nullable',
                'integer',
                Rule::exists('conversations', 'id')->where(fn (Builder $query): Builder => $query
                    ->where('bot_id', $this->routeBot()->id)
                    ->where('visitor_hash', $this->visitorHash())),
            ],
        ];
    }

    public function question(): string
    {
        return $this->string('question')->toString();
    }

    public function conversationId(): ?int
    {
        return $this->filled('conversation_id') ? $this->integer('conversation_id') : null;
    }

    public function visitorHash(): string
    {
        return app(VisitorHasher::class)->hash($this->ip(), $this->userAgent());
    }

    private function routeBot(): Bot
    {
        /** @var Bot */
        return $this->route('bot');
    }
}
