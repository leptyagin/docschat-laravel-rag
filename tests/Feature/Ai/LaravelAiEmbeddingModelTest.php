<?php

declare(strict_types=1);

use App\Ai\LaravelAiEmbeddingModel;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

function embeddingModel(): LaravelAiEmbeddingModel
{
    return new LaravelAiEmbeddingModel(provider: 'openai', model: 'configured-model', dimensions: 8, timeout: 10);
}

it('returns one vector per text with the configured dimensions', function (): void {
    Embeddings::fake();

    $result = embeddingModel()->embed(['first', 'second']);

    expect($result->vectors)->toHaveCount(2)
        ->and($result->vectors[0])->toHaveCount(8)
        ->and($result->model)->toBe('configured-model');

    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->inputs === ['first', 'second']
        && $prompt->dimensions === 8);
});

it('rejects a provider response that does not match the input count', function (): void {
    Embeddings::fake([[[0.1, 0.2]]]);

    embeddingModel()->embed(['first', 'second']);
})->throws(UnexpectedValueException::class);
