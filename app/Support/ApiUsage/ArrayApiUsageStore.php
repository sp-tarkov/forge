<?php

declare(strict_types=1);

namespace App\Support\ApiUsage;

use App\Contracts\ApiUsageStore;

/**
 * In-memory {@see ApiUsageStore} used where Redis is not available (notably the test suite).
 *
 * State lives on the instance, so this must be bound as a singleton to accumulate counters across the requests of a
 * single test. It is deliberately simple and not safe across processes; the Redis implementation is used anywhere
 * counters must be shared (production, local with Redis).
 */
final class ArrayApiUsageStore implements ApiUsageStore
{
    /**
     * @var array<string, array{requests: array<string, int>, latency: array<string, int>, histogram: array<string, int>, clients: array<string, int>}>
     */
    private array $buckets = [];

    public function record(string $bucket, string $dimension, int $latencyMs, string $latencyColumn, ?string $ip): void
    {
        if (! isset($this->buckets[$bucket])) {
            $this->buckets[$bucket] = ['requests' => [], 'latency' => [], 'histogram' => [], 'clients' => []];
        }

        $this->buckets[$bucket]['requests'][$dimension] = ($this->buckets[$bucket]['requests'][$dimension] ?? 0) + 1;
        $this->buckets[$bucket]['latency'][$dimension] = ($this->buckets[$bucket]['latency'][$dimension] ?? 0) + $latencyMs;

        $histogramField = $dimension.'|'.$latencyColumn;
        $this->buckets[$bucket]['histogram'][$histogramField] = ($this->buckets[$bucket]['histogram'][$histogramField] ?? 0) + 1;

        if ($ip !== null) {
            $this->buckets[$bucket]['clients'][$ip] = ($this->buckets[$bucket]['clients'][$ip] ?? 0) + 1;
        }
    }

    public function pendingBuckets(): array
    {
        // Numeric bucket identifiers become integer array keys in PHP; cast back to string to honour the contract.
        return array_map(strval(...), array_keys($this->buckets));
    }

    public function readBucket(string $bucket): array
    {
        return $this->buckets[$bucket] ?? ['requests' => [], 'latency' => [], 'histogram' => [], 'clients' => []];
    }

    public function forgetBucket(string $bucket): void
    {
        unset($this->buckets[$bucket]);
    }
}
