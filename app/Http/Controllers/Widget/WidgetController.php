<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widget\ShowWidgetRequest;
use App\Models\Bot;
use Illuminate\Http\Response;

final class WidgetController extends Controller
{
    /**
     * The page is made to be framed by any site, and nothing else in the
     * application is. The policy limits it to its own script and API.
     */
    public function show(ShowWidgetRequest $request, Bot $bot): Response
    {
        $config = [
            'botName' => $bot->name,
            'greeting' => $request->string('greeting')->toString() ?: $bot->greeting,
            'color' => $request->string('color')->toString() ?: $bot->theme['color'],
            'suggestedQuestions' => $bot->suggested_questions,
            'maxQuestionLength' => config()->integer('docschat.answers.max_question_length'),
            'messagesUrl' => route('api.v1.messages.store', $bot->public_key),
            'feedbackUrl' => route('api.v1.feedback.store', [$bot->public_key, '__MESSAGE__']),
            'storageKey' => "docschat:{$bot->public_key}",
        ];

        return response()
            ->view('widget', ['bot' => $bot, 'config' => $config])
            ->header('Content-Security-Policy', "default-src 'none'; script-src 'self'; style-src 'unsafe-inline'; connect-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'none'; frame-ancestors *");
    }
}
