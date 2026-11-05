<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Contracts\EmbeddingModel;
use App\Ai\Data\EmbeddingResult;
use Illuminate\Container\Attributes\Config;
use Laravel\Ai\Embeddings;
use UnexpectedValueException;

final readonly class LaravelAiEmbeddingModel implements EmbeddingModel
{
    public function __construct(
        #[Config('docschat.ai.embeddings.provider')] private string $provider,
        #[Config('docschat.ai.embeddings.model')] private string $model,
        #[Config('docschat.ai.embeddings.dimensions')] private int $dimensions,
        #[Config('docschat.ai.embeddings.timeout')] private int $timeout,
    ) {}

    public function name(): string
    {
        return $this->model;
    }

    public function embed(array $texts): EmbeddingResult
    {
        $response = Embeddings::for($texts)
            ->dimensions($this->dimensions)
            ->timeout($this->timeout)
            ->generate($this->provider, $this->model);

        if (count($response->embeddings) !== count($texts)) {
            throw new UnexpectedValueException(sprintf(
                'Expected %d embeddings from [%s], received %d.',
                count($texts),
                $this->provider,
                count($response->embeddings),
            ));
        }

        return new EmbeddingResult(
            vectors: array_values(array_map(array_values(...), $response->embeddings)),
            model: $this->model,
            promptTokens: $response->usage->inputTokens,
        );
    }
}
