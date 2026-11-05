<?php

declare(strict_types=1);

namespace App\Data\Answer;

use App\Models\Chunk;

/**
 * A document passage cited by an answer, as shown to the visitor.
 */
final readonly class Source
{
    /**
     * @param  int  $number  The number used in the answer, as in "[1]".
     */
    public function __construct(
        public int $number,
        public int $chunkId,
        public string $title,
        public ?int $page,
        public ?string $url,
    ) {}

    public static function fromChunk(int $number, Chunk $chunk): self
    {
        return new self(
            number: $number,
            chunkId: $chunk->id,
            title: $chunk->document->title,
            page: $chunk->page,
            url: $chunk->document->source_url,
        );
    }

    /**
     * @return array{number: int, title: string, page: int|null, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'title' => $this->title,
            'page' => $this->page,
            'url' => $this->url,
        ];
    }
}
