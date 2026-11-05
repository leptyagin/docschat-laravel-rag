<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Web;

interface HostResolver
{
    /**
     * @return list<string> Every IP address the host resolves to.
     */
    public function resolve(string $host): array;
}
