<?php

declare(strict_types=1);

namespace App\Http\Requests\Widget;

use Illuminate\Foundation\Http\FormRequest;

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
}
