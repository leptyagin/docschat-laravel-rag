<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bot>
 */
class BotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'public_key' => Bot::generatePublicKey(),
            'greeting' => 'Hi! How can I help you today?',
            'fallback_contact' => fake()->safeEmail(),
            'theme' => ['color' => '#0f766e', 'position' => 'right'],
            'suggested_questions' => ['Can I bring my dog?'],
            'embed_origins' => [],
            'documents_public' => true,
        ];
    }
}
