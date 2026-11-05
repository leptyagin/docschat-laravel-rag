<?php

declare(strict_types=1);

namespace App\Data\Ingestion;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The text of a source, normalised so that every later stage is format-agnostic.
 */
final readonly class ExtractedDocument implements Castable
{
    /**
     * @param  list<ExtractedPage>  $pages
     */
    public function __construct(
        public array $pages,
        public ?string $title = null,
    ) {}

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

    /**
     * @param  array<string, mixed>  $arguments
     * @return CastsAttributes<self, self>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            /**
             * @param  array<string, mixed>  $attributes
             */
            public function get(Model $model, string $key, mixed $value, array $attributes): ?ExtractedDocument
            {
                if (! is_string($value)) {
                    return null;
                }

                /** @var array{title: string|null, pages: list<array{text: string, number: int|null}>} $data */
                $data = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

                return new ExtractedDocument(
                    pages: array_map(
                        fn (array $page): ExtractedPage => new ExtractedPage($page['text'], $page['number']),
                        $data['pages'],
                    ),
                    title: $data['title'],
                );
            }

            /**
             * @param  array<string, mixed>  $attributes
             */
            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                if ($value === null) {
                    return null;
                }

                if (! $value instanceof ExtractedDocument) {
                    throw new InvalidArgumentException('The given value is not an ExtractedDocument.');
                }

                return json_encode([
                    'title' => $value->title,
                    'pages' => array_map(
                        fn (ExtractedPage $page): array => ['text' => $page->text, 'number' => $page->number],
                        $value->pages,
                    ),
                ], JSON_THROW_ON_ERROR);
            }
        };
    }
}
