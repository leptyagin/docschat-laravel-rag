<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Models\Bot;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bot_id' => Bot::factory(),
            'source_type' => DocumentSourceType::Text,
            'title' => fake()->sentence(3),
            'file_path' => 'documents/'.fake()->unique()->slug(2).'.md',
            'status' => DocumentStatus::Pending,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Ready,
            'checksum' => hash('sha256', fake()->sentence()),
            'chunk_count' => 1,
        ]);
    }

    public function fromUrl(string $url): static
    {
        return $this->state(fn (array $attributes): array => [
            'source_type' => DocumentSourceType::Url,
            'source_url' => $url,
            'file_path' => null,
        ]);
    }
}
