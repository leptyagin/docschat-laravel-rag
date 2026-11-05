<?php

declare(strict_types=1);

namespace App\Services\Answer;

use Illuminate\Container\Attributes\Config;

/**
 * Recognises a returning visitor without storing an IP address. The keyed hash
 * stops the small space of IP addresses from being reversed by brute force.
 */
final readonly class VisitorHasher
{
    public function __construct(
        #[Config('app.key')] private string $key,
    ) {}

    public function hash(?string $ipAddress, ?string $userAgent): string
    {
        return hash_hmac('sha256', $ipAddress.'|'.$userAgent, $this->key);
    }
}
