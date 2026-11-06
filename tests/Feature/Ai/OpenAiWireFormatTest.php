<?php

declare(strict_types=1);

use App\Ai\Contracts\ChatModel;
use App\Ai\Contracts\EmbeddingModel;
use App\Ai\Data\ChatRequest;
use App\Ai\LaravelAiChatModel;
use App\Ai\LaravelAiEmbeddingModel;
use App\Ai\OpenAiModerationModel;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
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

it('streams text and reports token usage from the real gateway', function (): void {
    useRealModels();
    $events = [
        ['type' => 'response.created', 'response' => ['id' => 'r1', 'model' => 'gpt-6-luna']],
        ['type' => 'response.output_item.added', 'item' => ['id' => 'm1', 'type' => 'message']],
        ['type' => 'response.output_text.delta', 'item_id' => 'm1', 'delta' => 'Hello '],
        ['type' => 'response.output_text.delta', 'item_id' => 'm1', 'delta' => 'there'],
        ['type' => 'response.output_text.done', 'item_id' => 'm1', 'text' => 'Hello there'],
        ['type' => 'response.completed', 'response' => ['id' => 'r1', 'status' => 'completed', 'output' => [], 'usage' => ['input_tokens' => 120, 'output_tokens' => 8]]],
    ];
    $body = implode('', array_map(fn (array $event): string => 'data: '.json_encode($event)."\n\n", $events));
    Http::fake(['api.openai.com/v1/responses' => Http::response($body, 200, ['Content-Type' => 'text/event-stream'])]);

    $stream = app(ChatModel::class)->stream(new ChatRequest('System rules.', 'Question?', maxTokens: 400));
    $deltas = iterator_to_array($stream, false);

    expect($deltas)->toBe(['Hello ', 'there'])
        ->and($stream->getReturn()->text)->toBe('Hello there')
        ->and($stream->getReturn()->promptTokens)->toBe(120)
        ->and($stream->getReturn()->completionTokens)->toBe(8);
});

it('switches thinking off and caps the answer in the request to Anthropic', function (): void {
    config()->set('ai.providers.anthropic.key', 'test-key');
    config()->set('docschat.ai.chat.provider', 'anthropic');
    config()->set('docschat.ai.chat.model', 'claude-haiku-5-5');
    app()->bind(ChatModel::class, LaravelAiChatModel::class);
    Http::fake(['api.anthropic.com/v1/messages' => Http::response([
        'id' => 'msg_1',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-haiku-5-5',
        'content' => [['type' => 'text', 'text' => 'Dogs are welcome [1].']],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 50, 'output_tokens' => 6],
    ])]);

    $response = app(ChatModel::class)->generate(new ChatRequest('System rules.', 'Question?', maxTokens: 400));

    expect($response->text)->toBe('Dogs are welcome [1].')
        ->and($response->promptTokens)->toBe(50);

    Http::assertSent(fn (Request $request): bool => $request->data()['model'] === 'claude-haiku-5-5'
        && $request->data()['max_tokens'] === 400
        && $request->data()['thinking'] === ['type' => 'disabled']);
});

it('requests embeddings with the configured model and dimensions', function (): void {
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

it('identifies the visitor to OpenAI', function (): void {
    useRealModels();
    Http::fake(['api.openai.com/v1/responses' => Http::response(openAiResponse())]);

    app(ChatModel::class)->generate(new ChatRequest('System rules.', 'Question?', maxTokens: 400, endUser: 'visitor-hash'));

    Http::assertSent(fn (Request $request): bool => $request->data()['safety_identifier'] === 'visitor-hash'
        && $request->data()['reasoning'] === ['effort' => 'none']);
});

it('never tells OpenAI anything about the visitor when none is given', function (): void {
    useRealModels();
    Http::fake(['api.openai.com/v1/responses' => Http::response(openAiResponse())]);

    app(ChatModel::class)->generate(new ChatRequest('System rules.', 'Question?', maxTokens: 400));

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('safety_identifier', $request->data()));
});

describe('moderation', function (): void {
    function realModeration(): OpenAiModerationModel
    {
        config()->set('ai.providers.openai.key', 'test-key');

        return app(OpenAiModerationModel::class);
    }

    it('asks the moderation endpoint with the key and reads its verdict', function (bool $flagged): void {
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/v1/moderations' => Http::response(['results' => [['flagged' => $flagged]]])]);

        expect(realModeration()->isFlagged('A question'))->toBe($flagged);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-key')
            && $request->data() === ['model' => 'omni-moderation-latest', 'input' => 'A question']);
    })->with([true, false]);

    it('lets a question through when the check itself fails', function (): void {
        Exceptions::fake();
        Http::fake(['api.openai.com/v1/moderations' => Http::response(status: 500)]);

        expect(realModeration()->isFlagged('A question'))->toBeFalse();

        Exceptions::assertReported(RequestException::class);
    });
});
