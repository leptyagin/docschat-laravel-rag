<?php

declare(strict_types=1);

namespace App\Data\Evals;

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

    public function containsExpectedWords(): bool
    {
        return array_all(
            $this->question->mustContain,
            fn (string $word): bool => mb_stripos($this->answer, $word) !== false,
        );
    }

    public function passed(): bool
    {
        return $this->question->shouldRefuse
            ? ! $this->answered
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
