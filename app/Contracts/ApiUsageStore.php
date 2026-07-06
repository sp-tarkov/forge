<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Fast, shared counter-store for API usage before it is rolled up.
 *
 * Implementations accumulate per-request counters under a minute "bucket" identifier and expose them for a scheduled
 * job to drain into the durable rollup tables. The production implementation is backed by Redis (atomic, shared
 * across all Octane workers and app servers); an in-memory implementation is used where Redis is unavailable.
 *
 * Counters are grouped per bucket into five flat string-keyed maps:
 * - requests: "{route}|{method}|{status}" => request count
 * - latency: "{route}|{method}|{status}" => summed latency in milliseconds
 * - histogram: "{route}|{method}|{status}|{lat_bN}" => count of requests falling in that latency bucket
 * - clients: "{ip}" => request count
 * - unmatched: "{method}|{status}|{path}" => request count (only requests that matched no route; path is last so a
 *   pipe inside the path cannot corrupt the dimension)
 */
interface ApiUsageStore
{
    /**
     * Record a single API request into the given minute bucket.
     *
     * @param  string  $bucket  Minute bucket identifier (UTC, format `YmdHi`).
     * @param  string  $dimension  The `{route}|{method}|{status}` key for this request.
     * @param  int  $latencyMs  Request latency in whole milliseconds.
     * @param  string  $latencyColumn  The histogram column (lat_b0..lat_b9) this request's latency falls into.
     * @param  string|null  $ip  The client IP, or null when it cannot be resolved.
     * @param  string|null  $unmatchedDimension  The `{method}|{status}|{path}` key when the request matched no route.
     */
    public function record(string $bucket, string $dimension, int $latencyMs, string $latencyColumn, ?string $ip, ?string $unmatchedDimension = null): void;

    /**
     * The bucket identifiers that currently hold counters awaiting rollup.
     *
     * @return list<string>
     */
    public function pendingBuckets(): array;

    /**
     * Read the accumulated counters for a single bucket.
     *
     * @return array{requests: array<string, int>, latency: array<string, int>, histogram: array<string, int>, clients: array<string, int>, unmatched: array<string, int>}
     */
    public function readBucket(string $bucket): array;

    /**
     * Discard every counter for a bucket once it has been persisted (or found empty).
     */
    public function forgetBucket(string $bucket): void;
}
