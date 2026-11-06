<?php

declare(strict_types=1);

namespace App\Services\Answer;

use Illuminate\Container\Attributes\Config;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Recognises a returning visitor without storing an IP address. The keyed hash
 * stops the small space of IP addresses from being reversed by brute force.
 */
final readonly class VisitorHasher
{
    /**
     * Keeps the first 48 bits. Providers hand out a /64, /56 or even a free /48
     * to one customer, so anything narrower would give them thousands of limits.
     */
    private const int IPV6_MASKED_BYTES = 10;

    /**
     * Keeps the first 24 bits: one rented server block usually sits in one /24.
     */
    private const int IPV4_SUBNET_MASKED_BYTES = 1;

    public function __construct(
        #[Config('app.key')] private string $key,
    ) {}

    public function hash(?string $ipAddress, ?string $userAgent): string
    {
        return hash_hmac('sha256', $ipAddress.'|'.$userAgent, $this->key);
    }

    /**
     * Identifies the network behind an address, for rate limits. An IPv4 address
     * counts as a network of its own.
     */
    public function hashNetwork(?string $ipAddress): string
    {
        return $this->hashMasked('network', (string) $ipAddress, 0);
    }

    /**
     * Identifies the wider block around an address, for a limit that a client
     * cannot avoid by moving to a neighbouring IPv4 address.
     */
    public function hashSubnet(?string $ipAddress): string
    {
        return $this->hashMasked('subnet', (string) $ipAddress, self::IPV4_SUBNET_MASKED_BYTES);
    }

    private function hashMasked(string $purpose, string $address, int $ipv4MaskedBytes): string
    {
        $masked = filter_var($address, FILTER_VALIDATE_IP) === false
            ? $address
            : IpUtils::anonymize($address, $ipv4MaskedBytes, self::IPV6_MASKED_BYTES);

        return hash_hmac('sha256', "{$purpose}|{$masked}", $this->key);
    }
}
