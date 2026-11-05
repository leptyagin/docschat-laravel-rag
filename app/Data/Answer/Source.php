<?php

declare(strict_types=1);

namespace App\Data\Answer;

use App\Models\Bot;
use App\Models\Chunk;
use Illuminate\Support\Str;

/**
 * A document passage cited by an answer.
 */
final readonly class Source
{
    private const int EXCERPT_LENGTH = 240;

    /**
     * @param  int  $number  The number used in the answer, as in "[1]".
     */
    public function __construct(
        public int $number,
        public int $chunkId,
        public string $title,
        public ?int $page,
        public ?string $url,
        public string $excerpt,
    ) {}

    public static function fromChunk(int $number, Chunk $chunk, Bot $bot): self
    {
        $document = $chunk->document;
        $fileUrl = route('widget.documents.show', [$bot->public_key, $document->id]);

        return new self(
            number: $number,
            chunkId: $chunk->id,
            title: $document->title,
            page: $chunk->page,
            url: $document->source_url ?? ($chunk->page === null ? $fileUrl : "{$fileUrl}#page={$chunk->page}"),
            excerpt: Str::limit($chunk->content, self::EXCERPT_LENGTH),
        );
    }

    /**
     * What a visitor sees.
     *
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

    /**
     * What is stored with the message. Re-indexing a document replaces its
     * chunks, so a past answer must keep its own record of what it cited.
     *
     * @return array{number: int, title: string, page: int|null, url: string|null, excerpt: string}
     */
    public function toSnapshot(): array
    {
        return [...$this->toArray(), 'excerpt' => $this->excerpt];
    }
}
