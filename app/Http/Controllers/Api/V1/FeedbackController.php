<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Answer\RecordFeedback;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFeedbackRequest;
use App\Models\Bot;
use App\Models\Message;
use Illuminate\Http\Response;

final class FeedbackController extends Controller
{
    public function store(StoreFeedbackRequest $request, Bot $bot, Message $message, RecordFeedback $recordFeedback): Response
    {
        $recordFeedback->handle($message, $request->feedback());

        return response()->noContent();
    }
}
