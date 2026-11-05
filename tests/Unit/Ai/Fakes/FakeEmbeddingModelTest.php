<?php

declare(strict_types=1);

use App\Ai\Fakes\FakeEmbeddingModel;

/**
 * @param  list<float>  $first
 * @param  list<float>  $second
 */
function cosineSimilarity(array $first, array $second): float
{
    return array_sum(array_map(fn (float $a, float $b): float => $a * $b, $first, $second));
}

it('places texts that share words closer than unrelated texts', function (): void {
    [$question, $related, $unrelated] = new FakeEmbeddingModel(dimensions: 256)->embed([
        'late checkout fee',
        'the late checkout fee is thirty dollars',
        'dogs are welcome at the spa',
    ])->vectors;

    expect(cosineSimilarity($question, $related))->toBeGreaterThan(cosineSimilarity($question, $unrelated));
});

it('produces the same unit vector for the same text', function (): void {
    $model = new FakeEmbeddingModel(dimensions: 64);

    $first = $model->embed(['parking'])->vectors[0];
    $second = $model->embed(['parking'])->vectors[0];

    expect($first)->toBe($second)
        ->and(cosineSimilarity($first, $first))->toEqualWithDelta(1.0, 0.0001);
});

it('returns a valid non-zero vector for text without words', function (): void {
    $vector = new FakeEmbeddingModel(dimensions: 8)->embed(['...'])->vectors[0];

    expect(array_sum($vector))->toBe(1.0);
});
