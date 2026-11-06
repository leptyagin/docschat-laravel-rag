<?php

declare(strict_types=1);

namespace App\Services\Answer;

/**
 * Names the network a visitor connects from, for rate limits. An IPv6 customer
 * is usually handed a whole /64, so counting single addresses would give them
 * billions of fresh limits. An IPv4 address is a network of its own.
 */
final readonly class VisitorNetwork
{
    private const string IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xFF\xFF";

    private const int IPV6_NETWORK_BYTES = 8;

    public function of(string $ipAddress): string
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ipAddress;
        }

        $packed = (string) inet_pton($ipAddress);

        if (str_starts_with($packed, self::IPV4_MAPPED_PREFIX)) {
            return (string) inet_ntop(substr($packed, strlen(self::IPV4_MAPPED_PREFIX)));
        }

        return bin2hex(substr($packed, 0, self::IPV6_NETWORK_BYTES)).'/64';
    }
}
