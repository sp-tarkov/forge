<?php

declare(strict_types=1);

use Amp\Cancellation;
use Amp\Dns\DnsException;
use Amp\Dns\DnsRecord;
use Amp\Dns\DnsResolver as AmpResolver;
use Amp\TimeoutCancellation;
use App\Support\Dns\AmpDnsResolver;

/**
 * Build an Amp resolver whose per-type answers are supplied as records, or as a throwable to raise.
 *
 * @param  array<int, list<string>|Throwable>  $answers  Keyed by DnsRecord::A / DnsRecord::AAAA.
 */
function fakeAmpResolver(array $answers): AmpResolver
{
    $resolver = Mockery::mock(AmpResolver::class);

    $resolver->shouldReceive('resolve')
        ->andReturnUsing(function (string $host, ?int $type, ?Cancellation $cancellation) use ($answers): array {
            $answer = $answers[$type] ?? new DnsException(sprintf('No records returned for %s', $host));

            throw_if($answer instanceof Throwable, $answer);

            return array_map(
                static fn (string $value): DnsRecord => new DnsRecord($value, $type ?? DnsRecord::A, 300),
                $answer,
            );
        });

    return $resolver;
}

it('returns both the ipv4 and ipv6 addresses of a host', function (): void {
    $resolver = new AmpDnsResolver(fakeAmpResolver([
        DnsRecord::A => ['93.184.215.14', '93.184.215.15'],
        DnsRecord::AAAA => ['2606:2800:21f:cb07::1'],
    ]), 5.0);

    expect($resolver->resolve('example.com'))
        ->toBe(['93.184.215.14', '93.184.215.15', '2606:2800:21f:cb07::1']);
});

it('returns the ipv4 addresses when a host has no ipv6 records', function (): void {
    $resolver = new AmpDnsResolver(fakeAmpResolver([
        DnsRecord::A => ['140.82.114.4'],
        DnsRecord::AAAA => new DnsException('No records returned for github.com (AAAA)'),
    ]), 5.0);

    expect($resolver->resolve('github.com'))->toBe(['140.82.114.4']);
});

it('returns the ipv6 addresses when a host has no ipv4 records', function (): void {
    $resolver = new AmpDnsResolver(fakeAmpResolver([
        DnsRecord::A => new DnsException('No records returned for v6only.example.com (A)'),
        DnsRecord::AAAA => ['2606:2800:21f:cb07::1'],
    ]), 5.0);

    expect($resolver->resolve('v6only.example.com'))->toBe(['2606:2800:21f:cb07::1']);
});

it('returns no addresses when the host does not resolve', function (): void {
    $resolver = new AmpDnsResolver(fakeAmpResolver([
        DnsRecord::A => new DnsException('Name resolution failed; server returned error code: 3 (NXDomain)'),
        DnsRecord::AAAA => new DnsException('Name resolution failed; server returned error code: 3 (NXDomain)'),
    ]), 5.0);

    expect($resolver->resolve('dead.example.com'))->toBe([]);
});

it('returns no addresses when resolution fails unexpectedly', function (): void {
    $resolver = new AmpDnsResolver(fakeAmpResolver([
        DnsRecord::A => new RuntimeException('nameserver exploded'),
        DnsRecord::AAAA => new RuntimeException('nameserver exploded'),
    ]), 5.0);

    expect($resolver->resolve('example.com'))->toBe([]);
});

it('bounds every query with the configured timeout', function (): void {
    $seen = [];

    $amp = Mockery::mock(AmpResolver::class);
    $amp->shouldReceive('resolve')
        ->andReturnUsing(function (string $host, ?int $type, ?Cancellation $cancellation) use (&$seen): array {
            $seen[] = $cancellation;

            return [];
        });

    new AmpDnsResolver($amp, 5.0)->resolve('example.com');

    expect($seen)->toHaveCount(2);
    expect($seen[0])->toBeInstanceOf(TimeoutCancellation::class);
    expect($seen[1])->toBeInstanceOf(TimeoutCancellation::class);
});
