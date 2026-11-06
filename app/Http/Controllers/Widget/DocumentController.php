<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget;

use App\Enums\DocumentSourceType;
use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\Document;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentController extends Controller
{
    /**
     * Lets a visitor open the file behind a source, if the bot publishes it. The
     * content type is fixed by the document type and the sandbox stops any active
     * content in the file.
     */
    public function show(
        Bot $bot,
        Document $document,
        FilesystemFactory $filesystem,
        #[Config('docschat.ingestion.disk')] string $disk,
    ): StreamedResponse {
        /** @var FilesystemAdapter $storage */
        $storage = $filesystem->disk($disk);

        abort_unless($bot->publishes($document) && $storage->exists((string) $document->file_path), 404);

        return $storage->response((string) $document->file_path, basename((string) $document->file_path), [
            'Content-Type' => $document->source_type === DocumentSourceType::Pdf ? 'application/pdf' : 'text/plain; charset=utf-8',
            'Content-Security-Policy' => 'sandbox',
        ], 'inline');
    }
}
