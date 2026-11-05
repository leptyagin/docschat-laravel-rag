<?php

declare(strict_types=1);

namespace App\Services\Answer;

use App\Data\Answer\RetrievedChunk;
use App\Data\Answer\Source;

final class CitationExtractor
{
    /**
     * Finds the context items an answer cites. A number outside the context is
     * a hallucinated citation and does not count, so an answer that cites only
     * such numbers is treated as uncited.
     *
     * @param  list<RetrievedChunk>  $chunks
     * @return list<Source> In order of first citation.
     */
    public function extract(string $answer, array $chunks): array
    {
        preg_match_all('/\[(\d+)]/', $answer, $matches);

        $sources = [];

        foreach (array_unique(array_map(intval(...), $matches[1])) as $number) {
            if (isset($chunks[$number - 1])) {
                $sources[] = Source::fromChunk($number, $chunks[$number - 1]->chunk);
            }
        }

        return $sources;
    }
}
