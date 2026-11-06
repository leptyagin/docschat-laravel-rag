<?php

declare(strict_types=1);

namespace App\Http\Requests\Widget;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * The parameters come from the page that embeds the widget.
 */
final class ShowWidgetRequest extends FormRequest
{
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
            'color' => ['nullable', 'string', 'hex_color'],
            'greeting' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * The page has no form to go back to, and a redirect would load the referring
     * site inside the frame.
     */
    protected function failedValidation(Validator $validator): never
    {
        abort(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
