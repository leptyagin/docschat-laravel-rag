<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Extractors;

use App\Data\Ingestion\ExtractedDocument;
use App\Exceptions\Ingestion\UnprocessableSourceException;
use App\Models\Document;
use App\Services\Ingestion\Web\RobotsPolicy;
use App\Services\Ingestion\Web\UrlFetcher;
use Illuminate\Http\Client\Response;

final readonly class WebPageExtractor implements TextExtractor
{
    private const array RETRYABLE_CLIENT_ERRORS = [408, 425, 429];

    private const array HTML_MIME_TYPES = ['text/html', 'application/xhtml+xml'];

    public function __construct(
        private RobotsPolicy $robots,
        private UrlFetcher $fetcher,
        private HtmlContentExtractor $html,
    ) {}

    public function extract(Document $document): ExtractedDocument
    {
        $response = $this->fetcher->get((string) $document->source_url, function (string $url): void {
            if (! $this->robots->allows($url)) {
                throw new UnprocessableSourceException('The site does not allow this page to be crawled (robots.txt).');
            }
        });

        $this->ensureUsable($response);

        return $this->html->extract($response->body(), $this->charsetOf($response));
    }

    private function charsetOf(Response $response): ?string
    {
        return preg_match('/charset=["\']?([\w.:-]+)/i', $response->header('Content-Type'), $matches) === 1 ? $matches[1] : null;
    }

    private function ensureUsable(Response $response): void
    {
        if ($response->clientError() && ! in_array($response->status(), self::RETRYABLE_CLIENT_ERRORS, true)) {
            throw new UnprocessableSourceException("The page could not be fetched (HTTP {$response->status()}).");
        }

        $response->throw();

        $mimeType = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));

        if (! in_array($mimeType, self::HTML_MIME_TYPES, true)) {
            throw new UnprocessableSourceException('The URL does not point to a web page.');
        }
    }
}
