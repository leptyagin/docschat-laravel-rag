<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Answer\OpenConversation;
use App\Actions\Answer\StreamAnswer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMessageRequest;
use App\Models\Bot;
use Illuminate\Contracts\Routing\ResponseFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MessageController extends Controller
{
    /**
     * Laravel's eventStream() stops reading once the client leaves, which would
     * drop a paid answer and its usage record. This stream always runs to the end.
     */
    public function store(
        StoreMessageRequest $request,
        Bot $bot,
        OpenConversation $openConversation,
        StreamAnswer $streamAnswer,
        ResponseFactory $responses,
    ): StreamedResponse {
        $conversation = $openConversation->handle($bot, $request->visitorHash(), $request->conversationId());
        $question = $request->question();

        return $responses->stream(function () use ($streamAnswer, $conversation, $question): void {
            ignore_user_abort(true);

            foreach ($streamAnswer->handle($conversation, $question) as $event) {
                echo $event->toServerSentEvent();

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
