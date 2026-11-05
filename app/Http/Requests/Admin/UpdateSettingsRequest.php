<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSettingsRequest extends FormRequest
{
    private const int MAX_SUGGESTED_QUESTIONS = 4;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'greeting' => ['required', 'string', 'max:300'],
            'color' => ['required', 'string', 'hex_color'],
            'fallback_contact' => ['required', 'string', 'max:255'],
            'suggested_questions' => ['array', 'max:'.self::MAX_SUGGESTED_QUESTIONS],
            'suggested_questions.*' => ['string', 'max:120'],
        ];
    }

    /**
     * The form sends one question per line.
     */
    protected function prepareForValidation(): void
    {
        $lines = preg_split('/\R/', $this->string('suggested_questions')->toString()) ?: [];

        $this->merge(['suggested_questions' => array_values(array_filter(array_map(trim(...), $lines)))]);
    }

    /**
     * @return list<string>
     */
    public function suggestedQuestions(): array
    {
        /** @var list<string> */
        return $this->validated('suggested_questions', []);
    }
}
