<?php

declare(strict_types=1);

namespace App\Ai\Fakes;

use App\Ai\Contracts\ChatModel;
use App\Ai\Data\ChatRequest;
use App\Ai\Data\ChatResponse;
use Generator;

/**
 * Runs the application locally without a provider or an API key.
 */
final class FakeChatModel implements ChatModel
{
    private const string MODEL = 'fake-chat';

    private const string ANSWER = 'This is a local fake answer, based on the first document found [1].';

    public function name(): string
    {
        return self::MODEL;
    }

    public function generate(ChatRequest $request): ChatResponse
    {
        return new ChatResponse(
            text: self::ANSWER,
            model: self::MODEL,
            promptTokens: str_word_count($request->systemPrompt.' '.$request->prompt),
            completionTokens: str_word_count(self::ANSWER),
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
