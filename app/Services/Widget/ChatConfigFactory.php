<?php

declare(strict_types=1);

namespace App\Services\Widget;

use App\Models\Bot;
use Illuminate\Container\Attributes\Config;

/**
 * What the chat needs to run, wherever it is shown.
 */
final readonly class ChatConfigFactory
{
    public function __construct(
        #[Config('docschat.demo.bot_slug')] private string $demoBotSlug,
        #[Config('docschat.demo.visitor_notice')] private string $demoVisitorNotice,
        #[Config('docschat.answers.max_question_length')] private int $maxQuestionLength,
    ) {}

    /**
     * @return array{botName: string, greeting: string, color: string, suggestedQuestions: list<string>, maxQuestionLength: int, messagesUrl: string, feedbackUrl: string, storageKey: string, notice: string|null}
     */
    public function for(Bot $bot, ?string $greeting = null, ?string $color = null): array
    {
        return [
            'botName' => $bot->name,
            'greeting' => $greeting ?: $bot->greeting,
            'color' => $color ?: $bot->theme['color'],
            'suggestedQuestions' => $bot->suggested_questions,
            'maxQuestionLength' => $this->maxQuestionLength,
            'messagesUrl' => route('api.v1.messages.store', $bot->public_key),
            'feedbackUrl' => route('api.v1.feedback.store', [$bot->public_key, '__MESSAGE__']),
            'storageKey' => "docschat:{$bot->public_key}",
            'notice' => $bot->slug === $this->demoBotSlug ? $this->demoVisitorNotice : null,
        ];
    }
}
