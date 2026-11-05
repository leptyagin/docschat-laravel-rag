<?php

declare(strict_types=1);

namespace App\Actions\Answer;

use App\Enums\MessageFeedback;
use App\Models\Message;

final class RecordFeedback
{
    public function handle(Message $message, MessageFeedback $feedback): void
    {
        $message->update(['feedback' => $feedback]);
    }
}
