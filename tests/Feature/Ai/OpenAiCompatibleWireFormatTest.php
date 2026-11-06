<?php

declare(strict_types=1);

use App\Ai\Contracts\ChatModel;
use App\Ai\Contracts\EmbeddingModel;
use App\Ai\Data\ChatRequest;
use App\Ai\LaravelAiChatModel;
use App\Ai\LaravelAiEmbeddingModel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * These tests run the SDK's real gateway and fake only the network, to pin down
 * what actually goes over the wire to a server that speaks the OpenAI API.
 */
beforeEach(function (): void {
    config()->set('ai.providers.openai-compatible.url', 'https://llm.test/v1');
    config()->set('ai.providers.openai-compatible.key', 'test-key');
    config()->set('docschat.ai.chat.provider', 'openai-compatible');
    config()->set('docschat.ai.chat.model', 'test/chat-model');
    config()->set('docschat.ai.chat.provider_options.openai-compatible', ['chat_template_kwargs' => ['enable_thinking' => false]]);
    config()->set('docschat.ai.embeddings.provider', 'openai-compatible');
    config()->set('docschat.ai.embeddings.model', 'test/embedding-model');

    app()->bind(ChatModel::class, LaravelAiChatModel::class);
    app()->bind(EmbeddingModel::class, LaravelAiEmbeddingModel::class);
    Http::preventStrayRequests();
});

it('sends the extra body and the answer cap, and reads the usage', function (): void {
    Http::fake(['llm.test/v1/chat/completions' => Http::response([
        'id' => 'chat_1',
        'object' => 'chat.completion',
        'model' => 'test/chat-model',
        'choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'Dogs are welcome [1].'],
            'finish_reason' => 'stop',
        ]],
        'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 8, 'total_tokens' => 128],
    ])]);

    $response = app(ChatModel::class)->generate(new ChatRequest('System rules.', 'Question?', maxTokens: 400));

    expect($response->text)->toBe('Dogs are welcome [1].')
        ->and($response->model)->toBe('test/chat-model')
        ->and($response->promptTokens)->toBe(120)
        ->and($response->completionTokens)->toBe(8);

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->hasHeader('Authorization', 'Bearer test-key')
            && $body['model'] === 'test/chat-model'
            && $body['max_tokens'] === 400
            && $body['chat_template_kwargs'] === ['enable_thinking' => false];
    });
});

it('asks a streamed answer for its usage, which the daily budget counts', function (): void {
    $events = [
        ['id' => 'chat_1', 'model' => 'test/chat-model', 'choices' => [['index' => 0, 'delta' => ['role' => 'assistant', 'content' => 'Hello ']]]],
        ['id' => 'chat_1', 'model' => 'test/chat-model', 'choices' => [['index' => 0, 'delta' => ['content' => 'there']]]],
        ['id' => 'chat_1', 'model' => 'test/chat-model', 'choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
        ['id' => 'chat_1', 'model' => 'test/chat-model', 'choices' => [], 'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 8]],
    ];
    $body = implode('', array_map(fn (array $event): string => 'data: '.json_encode($event)."\n\n", $events))."data: [DONE]\n\n";
    Http::fake(['llm.test/v1/chat/completions' => Http::response($body, 200, ['Content-Type' => 'text/event-stream'])]);

    $stream = app(ChatModel::class)->stream(new ChatRequest('System rules.', 'Question?', maxTokens: 400));
    $deltas = iterator_to_array($stream, false);

    expect($deltas)->toBe(['Hello ', 'there'])
        ->and($stream->getReturn()->text)->toBe('Hello there')
        ->and($stream->getReturn()->promptTokens)->toBe(120)
        ->and($stream->getReturn()->completionTokens)->toBe(8);

    Http::assertSent(fn (Request $request): bool => $request->data()['stream'] === true
        && $request->data()['stream_options'] === ['include_usage' => true]);
});

it('requests embeddings with the configured model and dimensions', function (): void {
    Http::fake(['llm.test/v1/embeddings' => Http::response([
        'object' => 'list',
        'data' => [
            ['object' => 'embedding', 'index' => 0, 'embedding' => [0.1, 0.2]],
            ['object' => 'embedding', 'index' => 1, 'embedding' => [0.3, 0.4]],
        ],
        'model' => 'test/embedding-model',
        'usage' => ['prompt_tokens' => 7, 'total_tokens' => 7],
    ])]);

    $result = app(EmbeddingModel::class)->embed(['first', 'second']);

    expect($result->vectors)->toBe([[0.1, 0.2], [0.3, 0.4]])
        ->and($result->promptTokens)->toBe(7);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-key')
        && $request->data()['model'] === 'test/embedding-model'
        && $request->data()['dimensions'] === 1536
        && $request->data()['input'] === ['first', 'second']);
});
