<?php

declare(strict_types=1);

namespace App\Ai\Fakes;

use App\Ai\Contracts\EmbeddingModel;
use App\Ai\Data\EmbeddingResult;
use Illuminate\Container\Attributes\Config;
use RuntimeException;

/**
 * Hashes each word into a dimension of a normalised vector. Texts that share
 * words end up close together, so retrieval behaves sensibly without a provider.
 */
final class FakeEmbeddingModel implements EmbeddingModel
{
    public const string MODEL = 'fake-embedding';

    /** @var list<list<string>> */
    public private(set) array $batches = [];

    private ?RuntimeException $failure = null;

    public function __construct(
        #[Config('docschat.ai.embeddings.dimensions')] private readonly int $dimensions = 1536,
    ) {}

    public function failWith(RuntimeException $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    public function embed(array $texts): EmbeddingResult
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->batches[] = $texts;

        return new EmbeddingResult(
            vectors: array_map($this->vectorFor(...), $texts),
            model: self::MODEL,
            promptTokens: array_sum(array_map(str_word_count(...), $texts)),
        );
    }

    /**
     * @return list<float>
     */
    private function vectorFor(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);

        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $words);

        foreach ($words[0] as $word) {
            $vector[crc32($word) % $this->dimensions] += 1.0;
        }

        $length = sqrt(array_sum(array_map(fn (float $value): float => $value ** 2, $vector)));

        if ($length === 0.0) {
            $vector[0] = 1.0;

            return $vector;
        }

        return array_map(fn (float $value): float => $value / $length, $vector);
    }
}
