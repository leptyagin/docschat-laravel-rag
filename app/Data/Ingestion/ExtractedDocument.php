<?php

declare(strict_types=1);

namespace App\Data\Ingestion;

/**
 * The text of a source, normalised so that every later stage is format-agnostic.
 */
final readonly class ExtractedDocument
{
    /**
     * @param  list<ExtractedPage>  $pages
     */
    public function __construct(
        public array $pages,
        public ?string $title = null,
    ) {}

    /**
     * @param  array{title: string|null, pages: list<array{text: string, number: int|null}>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            pages: array_map(fn (array $page): ExtractedPage => new ExtractedPage($page['text'], $page['number']), $data['pages']),
            title: $data['title'],
        );
    }

    /**
     * @return array{title: string|null, pages: list<array{text: string, number: int|null}>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'pages' => array_map(fn (ExtractedPage $page): array => ['text' => $page->text, 'number' => $page->number], $this->pages),
        ];
    }

    public function isEmpty(): bool
    {
        return array_all($this->pages, fn (ExtractedPage $page): bool => trim($page->text) === '');
    }

    /**
     * Hashes the text rather than the raw bytes, so a page whose markup changes
     * or a PDF that is merely re-exported does not trigger a paid re-embedding.
     */
    public function checksum(): string
    {
        return hash('sha256', implode("\f", array_map(
            fn (ExtractedPage $page): string => $page->text,
            $this->pages,
        )));
    }
}
