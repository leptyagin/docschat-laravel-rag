<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Web;

use App\Exceptions\Ingestion\UnprocessableSourceException;
use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;
use Uri\WhatWg\Url;

/**
 * Fetches URLs supplied by people, so it must not become a way into the private
 * network. Every hop is checked, and the connection is pinned to the checked
 * addresses so that a second DNS answer cannot redirect it (DNS rebinding).
 */
final readonly class UrlFetcher
{
    private const int MAX_REDIRECTS = 5;

    private const string TOO_LARGE_MESSAGE = 'The page is too large to ingest.';

    private const string NOT_PUBLIC_MESSAGE = 'Only public http and https URLs can be ingested.';

    /**
     * Not covered by PHP's global range filter: multicast, and NAT64, which
     * translates to any IPv4 address, including private ones.
     */
    private const array EXTRA_BLOCKED_RANGES = ['224.0.0.0/4', 'ff00::/8', '64:ff9b::/96', '64:ff9b:1::/48'];

    public function __construct(
        private HttpFactory $http,
        private HostResolver $resolver,
        private PublicUrlParser $parser,
        #[Config('docschat.ingestion.http.user_agent')] private string $userAgent,
        #[Config('docschat.ingestion.http.connect_timeout')] private int $connectTimeout,
        #[Config('docschat.ingestion.http.timeout')] private int $timeout,
        #[Config('docschat.ingestion.http.max_bytes')] private int $maxBytes,
    ) {}

    /**
     * @param  (Closure(string): void)|null  $beforeEachHop  Sees every URL about to be requested, redirects included.
     */
    public function get(string $url, ?Closure $beforeEachHop = null): Response
    {
        $target = $this->parser->parse($url);

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            if ($beforeEachHop !== null) {
                $beforeEachHop($target->toAsciiString());
            }

            $response = $this->request($target);
            $location = $response->header('Location');

            if (! $response->redirect() || $location === '') {
                return $response;
            }

            $target = $this->parser->parse($location, $target);
        }

        throw new UnprocessableSourceException('The page redirects too many times.');
    }

    private function request(Url $target): Response
    {
        $host = (string) $target->getAsciiHost();
        $port = PublicUrlParser::DEFAULT_PORTS[$target->getScheme()];
        $addresses = implode(',', $this->publicAddressesOf($host));
        $exceededSizeLimit = false;

        try {
            $response = $this->http
                ->withUserAgent($this->userAgent)
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->withoutRedirecting()
                ->withOptions([
                    /*
                    | The size limit counts bytes on the wire, so a compressed
                    | response could expand far past it once it was decoded.
                    */
                    'decode_content' => false,
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$addresses}"]],
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

    /**
     * @return list<string> Addresses in the format CURLOPT_RESOLVE expects.
     */
    private function publicAddressesOf(string $host): array
    {
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            throw new UnprocessableSourceException(self::NOT_PUBLIC_MESSAGE);
        }

        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new RuntimeException("The host [{$host}] could not be resolved.");
        }

        foreach ($addresses as $address) {
            if (! $this->isPublic($address)) {
                throw new UnprocessableSourceException(self::NOT_PUBLIC_MESSAGE);
            }
        }

        return array_map(fn (string $address): string => str_contains($address, ':') ? "[{$address}]" : $address, $addresses);
    }

    private function isPublic(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false
            && ! IpUtils::checkIp($address, self::EXTRA_BLOCKED_RANGES);
    }
}
