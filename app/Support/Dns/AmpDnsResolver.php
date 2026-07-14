<?php

declare(strict_types=1);

namespace App\Support\Dns;

use Amp\Dns\DnsException;
use Amp\Dns\DnsRecord;
use Amp\Dns\DnsResolver as AmpResolver;
use Amp\TimeoutCancellation;
use App\Contracts\DnsResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves hostnames with amphp/dns, which queries the nameservers configured in /etc/resolv.conf directly and
 * enforces a timeout on every attempt.
 *
 * PHP's own dns_get_record() cannot be used here: it takes no timeout and cannot be interrupted, so an unresponsive
 * nameserver blocks the caller for the resolver's entire retry budget.
 */
final readonly class AmpDnsResolver implements DnsResolver
{
    public function __construct(
        private AmpResolver $resolver,
        private float $timeout,
    ) {}

    public function resolve(string $host): array
    {
        return [
            ...$this->query($host, DnsRecord::A),
            ...$this->query($host, DnsRecord::AAAA),
        ];
    }

    /**
     * Query a single address family. Both families are queried because a host may point its A record at a public
     * address while pointing its AAAA record at an internal one, and either may be the address a redirect is
     * ultimately connected over.
     *
     * @return list<string>
     */
    private function query(string $host, int $type): array
    {
        try {
            $records = $this->resolver->resolve($host, $type, new TimeoutCancellation($this->timeout));
        } catch (DnsException) {
            // The host has no records of this type, does not exist, or could not be reached within the timeout.
            return [];
        } catch (Throwable $throwable) {
            Log::warning('DNS resolution failed unexpectedly', [
                'host' => $host,
                'type' => $type === DnsRecord::A ? 'A' : 'AAAA',
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }

        return array_map(
            static fn (DnsRecord $record): string => $record->getValue(),
            $records,
        );
    }
}
