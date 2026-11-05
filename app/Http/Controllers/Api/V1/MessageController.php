<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Answer\OpenConversation;
use App\Actions\Answer\StreamAnswer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMessageRequest;
use App\Http\Responses\AnswerEventStream;
use App\Models\Bot;

final class MessageController extends Controller
{
    public function store(
        StoreMessageRequest $request,
        Bot $bot,
        OpenConversation $openConversation,
        StreamAnswer $streamAnswer,
    ): AnswerEventStream {
        $conversation = $openConversation->handle($bot, $request->conversationToken(), $request->ip(), $request->userAgent());

        return new AnswerEventStream($streamAnswer->handle($conversation, $request->question()));
    }
}
