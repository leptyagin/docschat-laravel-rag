<?php

declare(strict_types=1);

namespace App\Ai\Contracts;

use App\Ai\Data\EmbeddingResult;

interface EmbeddingModel
{
    /**
     * Vectors come back in the order of the given texts.
     *
     * @param  non-empty-list<string>  $texts
     */
    public function embed(array $texts): EmbeddingResult;
}
