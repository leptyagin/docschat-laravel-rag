<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Data\Answer\RetrievedChunk;
use App\Data\Answer\Source;
use App\Models\Bot;

final class CitationExtractor
{
    /**
     * Finds the context items an answer cites, written as [1], [1][2] or [1, 2].
     * A number outside the context is a hallucinated citation and does not count,
     * so an answer that cites only such numbers is treated as uncited.
     *
     * @param  list<RetrievedChunk>  $chunks
     * @return list<Source> In order of first citation.
     */
    public function extract(string $answer, array $chunks, Bot $bot): array
    {
        preg_match_all('/\[(\d+(?:\s*,\s*\d+)*)]/', $answer, $matches);

        $numbers = array_map(intval(...), preg_split('/\s*,\s*/', implode(',', $matches[1]), flags: PREG_SPLIT_NO_EMPTY) ?: []);
        $sources = [];

        foreach (array_unique($numbers) as $number) {
            if (isset($chunks[$number - 1])) {
                $sources[] = Source::fromChunk($number, $chunks[$number - 1]->chunk, $bot);
            }
        }

        return $sources;
    }
}
