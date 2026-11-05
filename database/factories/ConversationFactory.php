<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bot;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bot_id' => Bot::factory(),
            'visitor_hash' => hash('sha256', fake()->uuid()),
        ];
    }
}
