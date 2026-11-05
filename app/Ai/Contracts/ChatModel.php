<?php

declare(strict_types=1);

namespace App\Ai\Contracts;

use App\Ai\Data\ChatRequest;
use App\Ai\Data\ChatResponse;
use Generator;

interface ChatModel
{
    /**
     * The model used when a request does not name one.
     */
    public function name(): string;

    public function generate(ChatRequest $request): ChatResponse;

    /**
     * Yields the answer as text deltas and returns the complete response.
     *
     * @return Generator<int, string, mixed, ChatResponse>
     */
    public function stream(ChatRequest $request): Generator;
}
