<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSettingsRequest extends FormRequest
{
    private const int MAX_SUGGESTED_QUESTIONS = 4;

    private const int MAX_EMBED_ORIGINS = 20;

    /**
     * Strict on purpose: the value goes into a Content-Security-Policy header.
     */
    private const string ORIGIN_PATTERN = '/\Ahttps?:\/\/[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?(?::\d{1,5})?\z/i';

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
            'name' => ['required', 'string', 'encoding:UTF-8', 'max:100'],
            'greeting' => ['required', 'string', 'encoding:UTF-8', 'max:300'],
            'color' => ['required', 'string', 'hex_color'],
            'fallback_contact' => ['required', 'string', 'encoding:UTF-8', 'max:255'],
            'suggested_questions' => ['array', 'max:'.self::MAX_SUGGESTED_QUESTIONS],
            'suggested_questions.*' => ['string', 'encoding:UTF-8', 'max:120'],
            'embed_origins' => ['array', 'max:'.self::MAX_EMBED_ORIGINS],
            'embed_origins.*' => ['string', 'regex:'.self::ORIGIN_PATTERN],
            'documents_public' => ['boolean'],
        ];
    }

    /**
     * The form sends one question and one site per line.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'suggested_questions' => $this->lines('suggested_questions'),
            'embed_origins' => array_map(
                fn (string $origin): string => rtrim(mb_strtolower($origin), '/'),
                $this->lines('embed_origins'),
            ),
        ]);
    }

    /**
     * @return list<string>
     */
    public function suggestedQuestions(): array
    {
        /** @var list<string> */
        return $this->validated('suggested_questions', []);
    }

    /**
     * @return list<string>
     */
    public function embedOrigins(): array
    {
        /** @var list<string> */
        return $this->validated('embed_origins', []);
    }

    /**
     * @return list<string>
     */
    private function lines(string $field): array
    {
        $lines = preg_split('/\R/', $this->string($field)->toString()) ?: [];

        return array_values(array_filter(array_map(trim(...), $lines)));
    }
}
