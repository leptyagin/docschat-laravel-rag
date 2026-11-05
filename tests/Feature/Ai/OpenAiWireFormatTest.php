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
 * These tests run the SDK's real OpenAI gateway and fake only the network, to
 * pin down what actually goes over the wire.
 */
function useRealModels(): void
{
    config()->set('ai.providers.openai.key', 'test-key');
    app()->bind(ChatModel::class, LaravelAiChatModel::class);
    app()->bind(EmbeddingModel::class, LaravelAiEmbeddingModel::class);
}

/**
 * @return array<string, mixed>
 */
function openAiResponse(): array
{
    return [
        'id' => 'resp_1',
        'object' => 'response',
        'status' => 'completed',
        'model' => 'gpt-6-luna',
        'output' => [[
            'id' => 'msg_1',
            'type' => 'message',
            'role' => 'assistant',
            'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => 'Dogs are welcome [1].', 'annotations' => []]],
        ]],
        'usage' => ['input_tokens' => 120, 'output_tokens' => 8, 'total_tokens' => 128],
    ];
}

it('switches reasoning off and caps the answer in the request to OpenAI', function (): void {
    Http::preventStrayRequests();
    useRealModels();
    Http::fake(['api.openai.com/v1/responses' => Http::response(openAiResponse())]);

    $response = app(ChatModel::class)->generate(new ChatRequest('System rules.', 'Question?', maxTokens: 400));

    expect($response->text)->toBe('Dogs are welcome [1].')
        ->and($response->promptTokens)->toBe(120)
        ->and($response->completionTokens)->toBe(8);

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $body['model'] === 'gpt-6-luna'
            && $body['max_output_tokens'] === 400
            && $body['reasoning'] === ['effort' => 'none']
            && ! array_key_exists('temperature', $body);
    });
});

it('requests embeddings with the configured model and dimensions', function (): void {
    Http::preventStrayRequests();
    useRealModels();
    Http::fake(['api.openai.com/v1/embeddings' => Http::response([
        'object' => 'list',
        'data' => [
            ['object' => 'embedding', 'index' => 0, 'embedding' => [0.1, 0.2]],
            ['object' => 'embedding', 'index' => 1, 'embedding' => [0.3, 0.4]],
        ],
        'model' => 'text-embedding-3-small',
        'usage' => ['prompt_tokens' => 7, 'total_tokens' => 7],
    ])]);

    $result = app(EmbeddingModel::class)->embed(['first', 'second']);

    expect($result->vectors)->toBe([[0.1, 0.2], [0.3, 0.4]])
        ->and($result->promptTokens)->toBe(7);

    Http::assertSent(fn (Request $request): bool => $request->data()['model'] === 'text-embedding-3-small'
        && $request->data()['dimensions'] === 1536
        && $request->data()['input'] === ['first', 'second']);
});
