<?php

declare(strict_types=1);

namespace App\Ai\Fakes;

use App\Ai\Contracts\ChatModel;
use App\Ai\Data\ChatRequest;
use App\Ai\Data\ChatResponse;
use Generator;
use RuntimeException;

/**
 * Replies with queued answers, then with a fixed one, and records every request.
 */
final class FakeChatModel implements ChatModel
{
    public const string MODEL = 'fake-chat';

    public const string DEFAULT_ANSWER = 'This is a local fake answer [1].';

    /** @var list<ChatRequest> */
    public private(set) array $requests = [];

    /** @var list<string> */
    private array $queuedAnswers = [];

    private ?RuntimeException $failure = null;

    public function respondWith(string ...$answers): self
    {
        array_push($this->queuedAnswers, ...array_values($answers));

        return $this;
    }

    public function failWith(RuntimeException $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    public function generate(ChatRequest $request): ChatResponse
    {
        $this->requests[] = $request;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        $answer = array_shift($this->queuedAnswers) ?? self::DEFAULT_ANSWER;

        return new ChatResponse(
            text: $answer,
            model: $request->model ?? self::MODEL,
            promptTokens: str_word_count($request->systemPrompt.' '.$request->prompt),
            completionTokens: str_word_count($answer),
        );
    }

    /**
     * @return Generator<int, string, mixed, ChatResponse>
     */
    public function stream(ChatRequest $request): Generator
    {
        $response = $this->generate($request);

        foreach (preg_split('/(?<=\s)/', $response->text, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $delta) {
            yield $delta;
        }

        return $response;
    }
}
