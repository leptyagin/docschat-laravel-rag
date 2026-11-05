<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Web;

use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Str;
use Spatie\Robots\RobotsTxt;

final readonly class RobotsPolicy
{
    public function __construct(
        private UrlFetcher $fetcher,
        #[Config('docschat.ingestion.http.user_agent')] private string $userAgent,
    ) {}

    /**
     * Follows RFC 9309: a missing robots.txt allows everything, while a server
     * error must be read as a disallow, so it surfaces here and is retried.
     */
    public function allows(string $url): bool
    {
        $robotsUrl = $this->fetcher->parse($url)
            ->withPath('/robots.txt')
            ->withQuery(null)
            ->withFragment(null)
            ->toAsciiString();

        $response = $this->fetcher->get($robotsUrl);

        if ($response->serverError() || $response->tooManyRequests()) {
            $response->throw();
        }

        if (! $response->successful()) {
            return true;
        }

        return new RobotsTxt($response->body())->allows($url, Str::before($this->userAgent, '/'));
    }
}
