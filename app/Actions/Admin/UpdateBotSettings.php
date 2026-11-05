<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Bot;

final class UpdateBotSettings
{
    /**
     * @param  list<string>  $suggestedQuestions
     */
    public function handle(
        Bot $bot,
        string $name,
        string $greeting,
        string $color,
        string $fallbackContact,
        array $suggestedQuestions,
    ): void {
        $bot->update([
            'name' => $name,
            'greeting' => $greeting,
            'fallback_contact' => $fallbackContact,
            'suggested_questions' => $suggestedQuestions,
            'theme' => [...$bot->theme, 'color' => $color],
        ]);
    }
}
