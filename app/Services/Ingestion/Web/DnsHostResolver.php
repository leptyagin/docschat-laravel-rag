<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Web;

final class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $addresses = [];

        foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
