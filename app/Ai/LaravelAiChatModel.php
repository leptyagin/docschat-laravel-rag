<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\ChatCompletionAgent;
use App\Ai\Contracts\ChatModel;
use App\Ai\Data\ChatRequest;
use App\Ai\Data\ChatResponse;
use Generator;
use Illuminate\Container\Attributes\Config;
use Laravel\Ai\Streaming\Events\TextDelta;

final readonly class LaravelAiChatModel implements ChatModel
{
    /**
     * @param  array<string, array<string, mixed>>  $providerOptions
     */
    public function __construct(
        #[Config('docschat.ai.chat.provider')] private string $provider,
        #[Config('docschat.ai.chat.model')] private string $model,
        #[Config('docschat.ai.chat.timeout')] private int $timeout,
        #[Config('docschat.ai.chat.provider_options')] private array $providerOptions = [],
    ) {}

    public function name(): string
    {
        return $this->model;
    }

    public function generate(ChatRequest $request): ChatResponse
    {
        $model = $request->model ?? $this->model;

        $response = (new ChatCompletionAgent($request, $this->providerOptions))->prompt(
            $request->prompt,
            provider: $this->provider,
            model: $model,
            timeout: $this->timeout,
        );

        return new ChatResponse(
            text: $response->text,
            model: $model,
            promptTokens: $response->usage->inputTokens,
            completionTokens: $response->usage->outputTokens,
        );
    }

    /**
     * @return Generator<int, string, mixed, ChatResponse>
     */
    public function stream(ChatRequest $request): Generator
    {
        $model = $request->model ?? $this->model;

        $stream = (new ChatCompletionAgent($request, $this->providerOptions))->stream(
            $request->prompt,
            provider: $this->provider,
            model: $model,
            timeout: $this->timeout,
        );

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                yield $event->delta;
            }
        }

        return new ChatResponse(
            text: $stream->text ?? '',
            model: $model,
            promptTokens: $stream->usage->inputTokens ?? 0,
            completionTokens: $stream->usage->outputTokens ?? 0,
        );
    }
}
