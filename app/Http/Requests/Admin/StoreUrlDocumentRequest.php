<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreUrlDocumentRequest extends FormRequest
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
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    public function sourceUrl(): string
    {
        return $this->string('url')->toString();
    }
}
