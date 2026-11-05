<?php

declare(strict_types=1);

namespace App\Data\Evals;

final readonly class EvalQuestion
{
    /**
     * @param  list<array{role: string, content: string}>  $history  Earlier turns, for follow-up questions.
     * @param  list<string>  $mustContain  Words the answer has to include.
     */
    public function __construct(
        public string $question,
        public array $history = [],
        public ?string $expectedDocument = null,
        public array $mustContain = [],
        public bool $shouldRefuse = false,
    ) {}
}
