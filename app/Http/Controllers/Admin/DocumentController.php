<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\DeleteDocument;
use App\Actions\Ingestion\IngestFile;
use App\Actions\Ingestion\IngestUrl;
use App\Actions\Ingestion\ReindexDocument;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDocumentRequest;
use App\Http\Requests\Admin\StoreUrlDocumentRequest;
use App\Models\Document;
use App\Services\Admin\ManagedBot;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class DocumentController extends Controller
{
    public function index(ManagedBot $managedBot): View
    {
        return view('admin.documents.index', [
            'documents' => $managedBot->get()->documents()
                ->select(['id', 'source_type', 'title', 'source_url', 'status', 'error', 'chunk_count', 'updated_at'])
                ->latest('updated_at')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function store(StoreDocumentRequest $request, ManagedBot $managedBot, IngestFile $ingestFile): RedirectResponse
    {
        $file = $request->document();

        return $this->queueing('file', fn (): Document => $ingestFile->handle(
            $managedBot->get(),
            $file->getRealPath(),
            $request->title(),
            $file->getClientOriginalName(),
        ));
    }

    public function storeUrl(StoreUrlDocumentRequest $request, ManagedBot $managedBot, IngestUrl $ingestUrl): RedirectResponse
    {
        return $this->queueing('url', fn (): Document => $ingestUrl->handle($managedBot->get(), $request->sourceUrl()));
    }

    public function reindex(Document $managedDocument, ReindexDocument $reindexDocument): RedirectResponse
    {
        $reindexDocument->handle($managedDocument);

        return back()->with('status', "“{$managedDocument->title}” is being indexed again.");
    }

    public function destroy(Document $managedDocument, DeleteDocument $deleteDocument): RedirectResponse
    {
        $deleteDocument->handle($managedDocument);

        return back()->with('status', "“{$managedDocument->title}” was deleted.");
    }

    /**
     * @param  Closure(): Document  $ingest
     */
    private function queueing(string $field, Closure $ingest): RedirectResponse
    {
        try {
            $document = $ingest();
        } catch (UnprocessableSourceException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }

        return back()->with('status', "“{$document->title}” was added and is being indexed.");
    }
}
