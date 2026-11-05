<?php

declare(strict_types=1);

namespace App\Ai\Contracts;

use App\Ai\Data\EmbeddingResult;

interface EmbeddingModel
{
    /**
     * The model name, recorded so that vectors from another model can be detected.
     */
    public function name(): string;

    /**
     * Vectors come back in the order of the given texts.
     *
     * @param  non-empty-list<string>  $texts
     */
    public function embed(array $texts): EmbeddingResult;
}
