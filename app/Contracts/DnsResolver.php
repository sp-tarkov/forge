<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Resolves a hostname to the IP addresses it points at.
 *
 * Verification resolves hostnames itself, before connecting, so a link can be rejected when it points at an internal
 * address and the connection can then be pinned to the exact address that was validated. That makes resolution part
 * of a request path and it must not be able to stall: the production implementation bounds every lookup with a
 * timeout, and an in-memory implementation is used in tests so they never touch a real nameserver.
 */
interface DnsResolver
{
    /**
     * Resolve a hostname to its A and AAAA addresses. Returns an empty list when the hostname does not resolve, or
     * when resolution fails or exceeds its time budget.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
