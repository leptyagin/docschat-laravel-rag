<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Data\Answer\AnswerEvent;
use App\Models\Message;
use Generator;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends an answer as server-sent events. Laravel's eventStream() stops reading
 * once the client leaves, which would drop a paid answer and its usage record,
 * so this stream always runs to the end. "no-transform" keeps the web server
 * from compressing, and thereby buffering, the stream.
 */
final readonly class AnswerEventStream implements Responsable
{
    /**
     * @param  Generator<int, AnswerEvent, mixed, Message>  $events
     */
    public function __construct(private Generator $events) {}

    public function toResponse($request): StreamedResponse
    {
        return new StreamedResponse(function (): void {
            ignore_user_abort(true);

            foreach ($this->events as $event) {
                echo $this->format($event);

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

    private function format(AnswerEvent $event): string
    {
        return sprintf("event: %s\ndata: %s\n\n", $event->type->value, json_encode($event->data, JSON_THROW_ON_ERROR));
    }
}
