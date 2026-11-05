<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Web;

use App\Exceptions\Ingestion\UnprocessableSourceException;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Uri\WhatWg\Url;

/**
 * Fetches URLs supplied by people, so it must not become a way into the private
 * network. Every hop is checked, and the connection is pinned to the checked
 * address so that a second DNS answer cannot redirect it (DNS rebinding).
 */
final readonly class UrlFetcher
{
    private const int MAX_REDIRECTS = 5;

    private const array DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    private const string TOO_LARGE_MESSAGE = 'The page is too large to ingest.';

    public function __construct(
        private HttpFactory $http,
        private HostResolver $resolver,
        #[Config('docschat.ingestion.http.user_agent')] private string $userAgent,
        #[Config('docschat.ingestion.http.connect_timeout')] private int $connectTimeout,
        #[Config('docschat.ingestion.http.timeout')] private int $timeout,
        #[Config('docschat.ingestion.http.max_bytes')] private int $maxBytes,
    ) {}

    public function get(string $url): Response
    {
        $target = $this->parse($url);

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $response = $this->request($target);
            $location = $response->header('Location');

            if (! $response->redirect() || $location === '') {
                return $response;
            }

            $target = $this->parse($location, $target);
        }

        throw new UnprocessableSourceException('The page redirects too many times.');
    }

    /**
     * Credentials and custom ports are refused along with other schemes: public
     * documentation needs neither, and both widen what a URL can reach.
     */
    public function parse(string $url, ?Url $base = null): Url
    {
        $parsed = Url::parse($url, $base);

        if ($parsed === null
            || ! isset(self::DEFAULT_PORTS[$parsed->getScheme()])
            || $parsed->getAsciiHost() === null
            || $parsed->getUsername() !== null
            || $parsed->getPassword() !== null
            || $parsed->getPort() !== null) {
            throw new UnprocessableSourceException('Only public http and https URLs can be ingested.');
        }

        return $parsed;
    }

    private function request(Url $target): Response
    {
        $host = (string) $target->getAsciiHost();
        $port = self::DEFAULT_PORTS[$target->getScheme()];
        $address = $this->publicAddressOf($host);
        $exceededSizeLimit = false;

        try {
            $response = $this->http
                ->withUserAgent($this->userAgent)
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->withoutRedirecting()
                ->withOptions([
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$address}"]],
                    'progress' => function (int $expected, int $received) use (&$exceededSizeLimit): bool {
                        return $exceededSizeLimit = $received > $this->maxBytes;
                    },
                ])
                ->get($target->toAsciiString());
        } catch (ConnectionException $exception) {
            throw $exceededSizeLimit ? new UnprocessableSourceException(self::TOO_LARGE_MESSAGE) : $exception;
        }

        if (strlen($response->body()) > $this->maxBytes) {
            throw new UnprocessableSourceException(self::TOO_LARGE_MESSAGE);
        }

        return $response;
    }

    private function publicAddressOf(string $host): string
    {
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            throw new UnprocessableSourceException('Only public http and https URLs can be ingested.');
        }

        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new RuntimeException("The host [{$host}] could not be resolved.");
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw new UnprocessableSourceException('Only public http and https URLs can be ingested.');
            }
        }

        return str_contains($addresses[0], ':') ? "[{$addresses[0]}]" : $addresses[0];
    }
}
