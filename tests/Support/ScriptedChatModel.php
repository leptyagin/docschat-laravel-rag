<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Ai\Contracts\ChatModel;
use App\Ai\Data\ChatRequest;
use App\Ai\Data\ChatResponse;
use Generator;
use RuntimeException;

/**
 * Replies with queued answers, then with a fixed one, and records every request.
 */
final class ScriptedChatModel implements ChatModel
{
    public const string MODEL = 'fake-chat';

    private const string DEFAULT_ANSWER = 'A scripted answer [1].';

    /** @var list<ChatRequest> */
    public private(set) array $requests = [];

    /** @var list<string> */
    private array $queuedAnswers = [];

    private ?RuntimeException $failure = null;

    private ?int $failAfterDeltas = null;

    private bool $reportsUsage = true;

    public function respondWith(string ...$answers): self
    {
        array_push($this->queuedAnswers, ...array_values($answers));

        return $this;
    }

    public function failWith(RuntimeException $failure, ?int $afterDeltas = null): self
    {
        $this->failure = $failure;
        $this->failAfterDeltas = $afterDeltas;

        return $this;
    }

    public function withoutUsage(): self
    {
        $this->reportsUsage = false;

        return $this;
    }

    public function name(): string
    {
        return self::MODEL;
    }

    public function generate(ChatRequest $request): ChatResponse
    {
        $this->requests[] = $request;

        if ($this->failure !== null && $this->failAfterDeltas === null) {
            throw $this->failure;
        }

        $answer = array_shift($this->queuedAnswers) ?? self::DEFAULT_ANSWER;

        return new ChatResponse(
            text: $answer,
            model: $request->model ?? self::MODEL,
            promptTokens: $this->reportsUsage ? str_word_count($request->systemPrompt.' '.$request->prompt) : 0,
            completionTokens: $this->reportsUsage ? str_word_count($answer) : 0,
        );
    }

    /**
     * @return Generator<int, string, mixed, ChatResponse>
     */
    public function stream(ChatRequest $request): Generator
    {
        $response = $this->generate($request);

        foreach (preg_split('/(?<=\s)/', $response->text, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $index => $delta) {
            if ($this->failure !== null && $index === $this->failAfterDeltas) {
                throw $this->failure;
            }

            yield $delta;
        }

        return $response;
    }
}
