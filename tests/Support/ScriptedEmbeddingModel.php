<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Ai\Contracts\EmbeddingModel;
use App\Ai\Data\EmbeddingResult;
use App\Ai\Fakes\FakeEmbeddingModel;
use RuntimeException;

/**
 * Embeds like the local fake, but records batches and can be told what to do.
 */
final class ScriptedEmbeddingModel implements EmbeddingModel
{
    /** @var list<list<string>> */
    public private(set) array $batches = [];

    private ?RuntimeException $failure = null;

    /** @var list<float>|null */
    private ?array $fixedVector = null;

    /**
     * @param  list<float>  $vector
     */
    public function returnVector(array $vector): self
    {
        $this->fixedVector = $vector;

        return $this;
    }

    public function failWith(RuntimeException $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    public function name(): string
    {
        return 'fake-embedding';
    }

    public function embed(array $texts): EmbeddingResult
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->batches[] = $texts;

        $result = new FakeEmbeddingModel()->embed($texts);

        return $this->fixedVector === null
            ? $result
            : new EmbeddingResult(array_fill(0, count($texts), $this->fixedVector), $result->model, $result->promptTokens);
    }
}
