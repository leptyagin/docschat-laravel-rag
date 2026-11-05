<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chunk>
 */
class ChunkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'bot_id' => fn (array $attributes): int => Document::query()->findOrFail($attributes['document_id'])->bot_id,
            'position' => 0,
            'heading' => fake()->sentence(3),
            'content' => fake()->paragraph(),
            'token_count' => 40,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'embedding' => array_fill(0, 1536, 0.01),
        ]);
    }
}
