<?php

declare(strict_types=1);

namespace App\Services\Evals;

use App\Actions\Answer\StreamAnswer;
use App\Data\Answer\AnswerEventType;
use App\Data\Evals\EvalQuestion;
use App\Data\Evals\EvalReport;
use App\Data\Evals\EvalResult;
use App\Enums\MessageRole;
use App\Models\Bot;
use App\Models\Conversation;
use Closure;
use Throwable;

/**
 * Sends questions through the same pipeline as visitors. The conversations are
 * removed afterwards, so a run leaves nothing in the statistics of the bot, and
 * a failing question is recorded rather than losing the paid results of the rest.
 */
final readonly class EvalRunner
{
    public function __construct(private StreamAnswer $streamAnswer) {}

    /**
     * @param  list<EvalQuestion>  $questions
     * @param  (Closure(): void)|null  $afterEach
     */
    public function run(Bot $bot, array $questions, ?Closure $afterEach = null): EvalReport
    {
        $results = [];

        foreach ($questions as $question) {
            $results[] = $this->ask($bot, $question);

            if ($afterEach !== null) {
                $afterEach();
            }
        }

        return new EvalReport($results);
    }

    private function ask(Bot $bot, EvalQuestion $question): EvalResult
    {
        $conversation = $bot->conversations()->create(['visitor_hash' => hash('sha256', 'docschat-eval')]);
        $conversation->setRelation('bot', $bot);

        try {
            $this->seedHistory($conversation, $question);

            $stream = $this->streamAnswer->handle($conversation, $question->question);
            $sources = [];

            foreach ($stream as $event) {
                if ($event->type === AnswerEventType::Completed) {
                    /** @var list<array{title: string}> $sources */
                    $sources = $event->data['sources'];
                }
            }

            $message = $stream->getReturn();

            return new EvalResult(
                question: $question,
                answer: $message->content,
                answered: $message->answered,
                refusalReason: $message->refusal_reason?->value,
                citedDocuments: array_values(array_unique(array_column($sources, 'title'))),
                costUsd: (string) $message->cost_usd,
                latencyMs: (int) $message->latency_ms,
            );
        } catch (Throwable $exception) {
            report($exception);

            return new EvalResult($question, $exception->getMessage(), false, 'exception', [], '0', 0);
        } finally {
            $conversation->delete();
        }
    }

    private function seedHistory(Conversation $conversation, EvalQuestion $question): void
    {
        foreach ($question->history as $turn) {
            $conversation->messages()->create([
                'role' => MessageRole::from($turn['role']),
                'content' => $turn['content'],
            ]);
        }
    }
}
