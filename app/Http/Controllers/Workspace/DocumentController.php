<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\Document;
use App\Services\Reader\DocumentLibrary;
use App\Services\Reader\DocumentOutliner;
use App\Services\Widget\ChatConfigFactory;
use Illuminate\Contracts\View\View;

final class DocumentController extends Controller
{
    /**
     * Opens what the file route of the same document already serves, on the
     * same bot key, with the assistant beside it.
     */
    public function show(
        Bot $bot,
        Document $document,
        DocumentOutliner $outliner,
        DocumentLibrary $library,
        ChatConfigFactory $chatConfig,
    ): View {
        abort_unless($bot->publishes($document), 404);

        return view('workspace.document', [
            'bot' => $bot,
            'document' => $outliner->outline($bot, $document),
            'library' => $library->for($bot),
            'chat' => $chatConfig->for($bot),
        ]);
    }
}
