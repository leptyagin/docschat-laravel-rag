<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Bot;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\View\View;

final class LandingController extends Controller
{
    /**
     * @param  array{repository_url: string|null, author_url: string|null, author_label: string|null}  $links
     */
    public function __invoke(
        #[Config('docschat.demo.bot_slug')] string $botSlug,
        #[Config('docschat.demo.links')] array $links,
        #[Config('docschat.demo.visitor_notice')] string $visitorNotice,
    ): View {
        return view('landing', [
            'bot' => Bot::query()->firstWhere('slug', $botSlug),
            'links' => $links,
            'visitorNotice' => $visitorNotice,
        ]);
    }
}
