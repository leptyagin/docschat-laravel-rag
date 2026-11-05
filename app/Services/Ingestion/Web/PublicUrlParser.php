<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Web;

use App\Exceptions\Ingestion\UnprocessableSourceException;
use Uri\WhatWg\Url;

/**
 * Accepts only URLs that can point at a public web page. Credentials and custom
 * ports are refused along with other schemes: public documentation needs neither,
 * and both widen what a URL can reach.
 */
final class PublicUrlParser
{
    public const array DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    private const int MAX_LENGTH = 2048;

    public function parse(string $url, ?Url $base = null): Url
    {
        $parsed = Url::parse($url, $base);

        if ($parsed === null
            || strlen($parsed->toAsciiString()) > self::MAX_LENGTH
            || ! isset(self::DEFAULT_PORTS[$parsed->getScheme()])
            || $parsed->getAsciiHost() === null
            || $parsed->getUsername() !== null
            || $parsed->getPassword() !== null
            || $parsed->getPort() !== null) {
            throw new UnprocessableSourceException('Only public http and https URLs can be ingested.');
        }

        return $parsed;
    }
}
