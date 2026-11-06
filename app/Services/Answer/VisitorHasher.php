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
        $address = (string) $ipAddress;

        $network = filter_var($address, FILTER_VALIDATE_IP) === false
            ? $address
            : IpUtils::anonymize($address, 0, self::IPV6_MASKED_BYTES);

        return hash_hmac('sha256', "network|{$network}", $this->key);
    }
}
