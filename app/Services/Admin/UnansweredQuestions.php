<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Data\Admin\QuestionGroup;
use App\Enums\MessageRole;
use App\Enums\RefusalReason;
use App\Models\Bot;
use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;

/**
 * Groups what the bot could not answer, so that the owner sees which documents
 * to add. Questions are compared by the words they share, after dropping words
 * that carry no topic, and only recent ones are read to keep the comparison cheap.
 */
final class UnansweredQuestions
{
    private const int RECENT_LIMIT = 300;

    private const float SAME_QUESTION_SIMILARITY = 0.5;

    private const array STOP_WORDS = [
        'a', 'about', 'an', 'and', 'any', 'are', 'at', 'be', 'can', 'do', 'does', 'for', 'have', 'how', 'i', 'in', 'is',
        'it', 'me', 'much', 'my', 'of', 'on', 'or', 'our', 'the', 'there', 'to', 'we', 'what', 'when', 'where', 'which',
        'who', 'why', 'will', 'with', 'you', 'your',
    ];

    /**
     * @return list<QuestionGroup> Most frequent first.
     */
    public function grouped(Bot $bot, ?int $limit = null): array
    {
        $groups = [];

        foreach ($this->recent($bot) as $message) {
            $words = $this->significantWords((string) $message->getAttribute('question'));
            $key = $this->findGroup($groups, $words);

            if ($key === null) {
                $groups[] = ['words' => $words, 'messages' => [$message]];

                continue;
            }

            $groups[$key]['messages'][] = $message;
        }

        usort($groups, fn (array $first, array $second): int => count($second['messages']) <=> count($first['messages']));

        return array_map($this->toQuestionGroup(...), array_slice($groups, 0, $limit));
    }

    /**
     * @return Collection<int, Message> Newest first, each with the question that caused it.
     */
    private function recent(Bot $bot): Collection
    {
        return Message::query()
            ->whereIn('conversation_id', $bot->conversations()->select('id'))
            ->where('role', MessageRole::Assistant)
            ->whereIn('refusal_reason', [RefusalReason::LowSimilarity, RefusalReason::NoCitation])
            ->select(['id', 'conversation_id', 'created_at'])
            ->addSelect(['question' => Message::query()
                ->from('messages as questions')
                ->select('questions.content')
                ->whereColumn('questions.conversation_id', 'messages.conversation_id')
                ->whereColumn('questions.id', '<', 'messages.id')
                ->where('questions.role', MessageRole::User)
                ->orderByDesc('questions.id')
                ->limit(1)])
            ->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    /**
     * @return list<string>
     */
    private function significantWords(string $question): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($question), $matches);

        return array_values(array_unique(array_diff($matches[0], self::STOP_WORDS)));
    }

    /**
     * @param  list<array{words: list<string>, messages: list<Message>}>  $groups
     * @param  list<string>  $words
     */
    private function findGroup(array $groups, array $words): ?int
    {
        foreach ($groups as $key => $group) {
            if ($this->similarity($group['words'], $words) >= self::SAME_QUESTION_SIMILARITY) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $first
     * @param  list<string>  $second
     */
    private function similarity(array $first, array $second): float
    {
        $union = count(array_unique([...$first, ...$second]));

        return $union === 0 ? 1.0 : count(array_intersect($first, $second)) / $union;
    }

    /**
     * @param  array{words: list<string>, messages: list<Message>}  $group
     */
    private function toQuestionGroup(array $group): QuestionGroup
    {
        $latest = $group['messages'][0];
        $questions = array_map(fn (Message $message): string => (string) $message->getAttribute('question'), $group['messages']);

        return new QuestionGroup(
            question: $questions[0],
            count: count($group['messages']),
            lastAskedAt: $latest->created_at,
            conversationId: $latest->conversation_id,
            variants: array_values(array_diff(array_unique($questions), [$questions[0]])),
        );
    }
}
