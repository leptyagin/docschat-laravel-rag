<?php

declare(strict_types=1);

namespace App\Services\Evals;

use App\Data\Evals\EvalQuestion;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

final class EvalSuite
{
    /**
     * @return list<EvalQuestion>
     */
    public function load(string $path): array
    {
        /** @var list<array<string, mixed>> $entries */
        $entries = Yaml::parseFile($path);

        return array_map($this->parse(...), $entries);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function parse(array $entry): EvalQuestion
    {
        $question = $entry['question'] ?? null;
        $expectedDocument = $entry['expected_document'] ?? null;
        $shouldRefuse = ($entry['should_refuse'] ?? false) === true;

        if (! is_string($question) || $question === '') {
            throw new InvalidArgumentException('Every eval entry needs a question.');
        }

        if ($shouldRefuse === is_string($expectedDocument)) {
            throw new InvalidArgumentException("[{$question}] needs either expected_document or should_refuse.");
        }

        /** @var list<array{role: string, content: string}> $history */
        $history = $entry['history'] ?? [];

        /** @var list<int|string> $mustContain */
        $mustContain = $entry['must_contain'] ?? [];

        return new EvalQuestion(
            question: $question,
            history: $history,
            expectedDocument: is_string($expectedDocument) ? $expectedDocument : null,
            mustContain: array_map(strval(...), $mustContain),
            shouldRefuse: $shouldRefuse,
        );
    }
}
