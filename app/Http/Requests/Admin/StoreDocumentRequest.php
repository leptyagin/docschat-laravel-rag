<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Container\Attributes\Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(#[Config('docschat.ingestion.max_file_bytes')] int $maxFileBytes): array
    {
        return [
            'file' => ['required', 'file', 'extensions:pdf,md,txt', 'max:'.intdiv($maxFileBytes, 1024)],
            'title' => ['nullable', 'string', 'encoding:UTF-8', 'max:255'],
        ];
    }

    public function document(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('file');
    }

    public function title(): ?string
    {
        return $this->filled('title') ? $this->string('title')->toString() : null;
    }
}
