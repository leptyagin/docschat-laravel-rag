<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widget\ShowWidgetRequest;
use App\Models\Bot;
use App\Services\Widget\ChatConfigFactory;
use Illuminate\Http\Response;

final class WidgetController extends Controller
{
    /**
     * The page is made to be framed by the sites the bot allows, any site by
     * default, and nothing else in the application is. The policy limits it to
     * its own script and API.
     */
    public function show(ShowWidgetRequest $request, Bot $bot, ChatConfigFactory $chatConfig): Response
    {
        $config = $chatConfig->for(
            $bot,
            greeting: $request->embeddedGreeting(),
            color: $request->string('color')->toString(),
        );

        return response()
            ->view('widget', ['bot' => $bot, 'config' => $config])
            ->header('Content-Security-Policy', "default-src 'none'; script-src 'self'; style-src 'unsafe-inline'; connect-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'none'; frame-ancestors {$bot->frameAncestors()}");
    }
}
