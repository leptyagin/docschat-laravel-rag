<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Services\Reader\DocumentLibrary;
use App\Services\Widget\ChatConfigFactory;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\View\View;

final class LandingController extends Controller
{
    /**
     * @param  array{repository_url: string|null, author_url: string|null, author_label: string|null}  $links
     */
    public function __invoke(
        DocumentLibrary $library,
        ChatConfigFactory $chatConfig,
        #[Config('docschat.demo.bot_slug')] string $botSlug,
        #[Config('docschat.demo.links')] array $links,
        #[Config('docschat.demo.visitor_notice')] string $visitorNotice,
    ): View {
        $bot = Bot::query()->firstWhere('slug', $botSlug);

        return view('landing', [
            'bot' => $bot,
            'library' => $bot === null ? collect() : $library->for($bot),
            'chat' => $bot === null ? null : $chatConfig->for($bot),
            'links' => $links,
            'visitorNotice' => $visitorNotice,
        ]);
    }
}
