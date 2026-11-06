<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\Answer\AnswerSlot;
use App\Services\Answer\AnswerSlots;
use App\Services\Answer\VisitorHasher;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a slot from before the answer starts until it has been streamed, which
 * happens after the response leaves the middleware, so the slot ends in terminate().
 */
final readonly class LimitConcurrentAnswers
{
    private const string SLOT_ATTRIBUTE = 'docschat.answer_slot';

    private const int RETRY_AFTER_SECONDS = 5;

    public function __construct(
        private AnswerSlots $slots,
        private VisitorHasher $visitors,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slot = $this->slots->acquire($this->visitors->hashNetwork($request->ip()));

        if ($slot === null) {
            return new JsonResponse(
                ['message' => 'The assistant is busy right now. Please try again in a moment.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => self::RETRY_AFTER_SECONDS],
            );
        }

        $request->attributes->set(self::SLOT_ATTRIBUTE, $slot);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $slot = $request->attributes->get(self::SLOT_ATTRIBUTE);

        if ($slot instanceof AnswerSlot) {
            $slot->release();
        }
    }
}
