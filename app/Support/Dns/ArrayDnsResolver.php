<?php

declare(strict_types=1);

namespace App\Support\Dns;

use App\Contracts\DnsResolver;

/**
 * In-memory {@see DnsResolver} used in tests so they never touch a real nameserver.
 *
 * Hosts with no explicit mapping resolve to a public address, which keeps tests that only care about a link being
 * reachable free of DNS setup. Map a host to an internal address to exercise the SSRF checks, or to an empty list to
 * make it unresolvable. State lives on the instance, so this must be bound as a singleton for a test's mappings to
 * survive across the requests it makes.
 */
final class ArrayDnsResolver implements DnsResolver
{
    /**
     * Addresses per hostname.
     *
     * @var array<string, list<string>>
     */
    private array $records = [];

    /**
     * @param  list<string>  $defaultAddresses  Addresses returned for any host without an explicit mapping.
     */
    public function __construct(private readonly array $defaultAddresses = ['93.184.215.14']) {}

    /**
     * Map a hostname to the addresses it resolves to. An empty list makes the host unresolvable.
     *
     * @param  list<string>  $addresses
     */
    public function set(string $host, array $addresses): void
    {
        $this->records[$host] = $addresses;
    }

    public function resolve(string $host): array
    {
        return $this->records[$host] ?? $this->defaultAddresses;
    }
}
