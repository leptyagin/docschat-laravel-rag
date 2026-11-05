<?php

declare(strict_types=1);

namespace App\Data\Admin;

use Carbon\CarbonInterface;

final readonly class QuestionGroup
{
    /**
     * @param  list<string>  $variants  Other wordings of the same question.
     */
    public function __construct(
        public string $question,
        public int $count,
        public CarbonInterface $lastAskedAt,
        public int $conversationId,
        public array $variants,
    ) {}
}
