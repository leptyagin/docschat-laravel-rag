<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Bot;

final class UpdateBotSettings
{
    /**
     * @param  list<string>  $suggestedQuestions
     * @param  list<string>  $embedOrigins
     */
    public function handle(
        Bot $bot,
        string $name,
        string $greeting,
        string $color,
        string $fallbackContact,
        array $suggestedQuestions,
        array $embedOrigins,
        bool $documentsPublic,
    ): void {
        $bot->update([
            'name' => $name,
            'greeting' => $greeting,
            'fallback_contact' => $fallbackContact,
            'suggested_questions' => $suggestedQuestions,
            'embed_origins' => $embedOrigins,
            'documents_public' => $documentsPublic,
            'theme' => [...$bot->theme, 'color' => $color],
        ]);
    }
}
