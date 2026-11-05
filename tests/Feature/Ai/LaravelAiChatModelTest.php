<?php

declare(strict_types=1);

use App\Ai\Agents\ChatCompletionAgent;
use App\Ai\Data\ChatRequest;
use App\Ai\LaravelAiChatModel;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Prompts\AgentPrompt;

function chatModel(): LaravelAiChatModel
{
    return new LaravelAiChatModel(
        provider: 'openai',
        model: 'configured-model',
        timeout: 10,
        providerOptions: ['openai' => ['reasoning' => ['effort' => 'none']]],
    );
}

function chatRequest(?string $model = null): ChatRequest
{
    return new ChatRequest(
        systemPrompt: 'Answer from the context.',
        prompt: 'Where can I park?',
        maxTokens: 400,
        model: $model,
    );
}

it('sends the prompt and maps the answer', function (): void {
    ChatCompletionAgent::fake(['Park behind the hotel [1].'])->preventStrayPrompts();

    $response = chatModel()->generate(chatRequest());

    expect($response->text)->toBe('Park behind the hotel [1].')
        ->and($response->model)->toBe('configured-model');

    ChatCompletionAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $agent = $prompt->agent;

        return $agent instanceof ChatCompletionAgent
            && $prompt->prompt === 'Where can I park?'
            && $agent->instructions() === 'Answer from the context.'
            && $agent->maxTokens() === 400;
    });
});

it('sends only the options of the provider in use', function (): void {
    ChatCompletionAgent::fake(['Answer']);

    chatModel()->generate(chatRequest());

    ChatCompletionAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $agent = $prompt->agent;

        return $agent instanceof ChatCompletionAgent
            && $agent->providerOptions(Lab::OpenAI) === ['reasoning' => ['effort' => 'none']]
            && $agent->providerOptions('anthropic') === [];
    });
});

it('uses the model requested by the caller over the configured one', function (): void {
    ChatCompletionAgent::fake(['Rewritten query']);

    expect(chatModel()->generate(chatRequest('cheap-model'))->model)->toBe('cheap-model');
});

it('streams text deltas and returns the complete answer', function (): void {
    ChatCompletionAgent::fake(['Park behind the hotel.']);

    $stream = chatModel()->stream(chatRequest());
    $deltas = implode('', iterator_to_array($stream, false));

    expect($deltas)->toBe('Park behind the hotel.')
        ->and($stream->getReturn()->text)->toBe('Park behind the hotel.');
});
