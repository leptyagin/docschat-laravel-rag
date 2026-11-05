<?php

declare(strict_types=1);

namespace App\Data\Evals;

use App\Enums\RefusalReason;

final readonly class EvalResult
{
    /**
     * @param  list<string>  $citedDocuments
     * @param  numeric-string  $costUsd
     */
    public function __construct(
        public EvalQuestion $question,
        public string $answer,
        public bool $answered,
        public ?string $refusalReason,
        public array $citedDocuments,
        public string $costUsd,
        public int $latencyMs,
    ) {}

    public function hitExpectedSource(): bool
    {
        return in_array($this->question->expectedDocument, $this->citedDocuments, true);
    }

    /**
     * An outage must not pass for a correct refusal.
     */
    public function isError(): bool
    {
        return ! $this->answered && RefusalReason::tryFrom((string) $this->refusalReason)?->isUnanswered() !== true;
    }

    public function isRefusal(): bool
    {
        return ! $this->answered && ! $this->isError();
    }

    /**
     * A number must stand alone, so that "40" does not match "140" or "400".
     */
    public function containsExpectedWords(): bool
    {
        return array_all(
            $this->question->mustContain,
            fn (string $word): bool => preg_match('/(?<!\p{N})'.preg_quote($word, '/').'(?!\p{N})/iu', $this->answer) === 1,
        );
    }

    public function passed(): bool
    {
        return $this->question->shouldRefuse
            ? $this->isRefusal()
            : $this->answered && $this->hitExpectedSource() && $this->containsExpectedWords();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'question' => $this->question->question,
            'expected_document' => $this->question->expectedDocument,
            'must_contain' => $this->question->mustContain,
            'should_refuse' => $this->question->shouldRefuse,
            'answer' => $this->answer,
            'answered' => $this->answered,
            'refusal_reason' => $this->refusalReason,
            'cited_documents' => $this->citedDocuments,
            'cost_usd' => $this->costUsd,
            'latency_ms' => $this->latencyMs,
            'passed' => $this->passed(),
        ];
    }
}
