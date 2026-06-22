<?php

declare(strict_types=1);

namespace App\Support\ApiUsage;

use App\Contracts\ApiUsageStore;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Redis;
use Redis as PhpRedis;
use RuntimeException;

/**
 * Redis-backed {@see ApiUsageStore}.
 *
 * Counters are stored as Redis hashes (one hash per bucket per category) plus a set that registers which buckets
 * still need rolling up. Writes are issued as a single pipeline so each tracked request costs one round trip. Every
 * data key carries a backstop TTL so a stalled rollup can never let the counters grow without bound.
 */
final readonly class RedisApiUsageStore implements ApiUsageStore
{
    /**
     * The set of bucket identifiers that still hold counters awaiting rollup.
     */
    private const string BUCKETS_KEY = 'au:buckets';

    public function __construct(
        private string $connection,
        private int $bucketTtl,
    ) {}

    public function record(string $bucket, string $dimension, int $latencyMs, string $latencyColumn, ?string $ip): void
    {
        $requestKey = $this->key($bucket, 'req');
        $latencyKey = $this->key($bucket, 'lat');
        $histogramKey = $this->key($bucket, 'hist');
        $clientKey = $this->key($bucket, 'ip');
        $ttl = $this->bucketTtl;

        $this->connection()->pipeline(function (PhpRedis $pipe) use (
            $bucket,
            $dimension,
            $latencyMs,
            $latencyColumn,
            $ip,
            $requestKey,
            $latencyKey,
            $histogramKey,
            $clientKey,
            $ttl,
        ): void {
            $pipe->sadd(self::BUCKETS_KEY, $bucket);
            $pipe->hincrby($requestKey, $dimension, 1);
            $pipe->hincrby($latencyKey, $dimension, $latencyMs);
            $pipe->hincrby($histogramKey, $dimension.'|'.$latencyColumn, 1);

            if ($ip !== null) {
                $pipe->hincrby($clientKey, $ip, 1);
            }

            $pipe->expire($requestKey, $ttl);
            $pipe->expire($latencyKey, $ttl);
            $pipe->expire($histogramKey, $ttl);
            $pipe->expire($clientKey, $ttl);
        });
    }

    public function pendingBuckets(): array
    {
        /** @var list<string> $members */
        $members = $this->connection()->smembers(self::BUCKETS_KEY);

        return array_map(strval(...), $members);
    }

    public function readBucket(string $bucket): array
    {
        return [
            'requests' => $this->readHash($this->key($bucket, 'req')),
            'latency' => $this->readHash($this->key($bucket, 'lat')),
            'histogram' => $this->readHash($this->key($bucket, 'hist')),
            'clients' => $this->readHash($this->key($bucket, 'ip')),
        ];
    }

    public function forgetBucket(string $bucket): void
    {
        $this->connection()->del(
            $this->key($bucket, 'req'),
            $this->key($bucket, 'lat'),
            $this->key($bucket, 'hist'),
            $this->key($bucket, 'ip'),
        );

        $this->connection()->srem(self::BUCKETS_KEY, $bucket);
    }

    /**
     * Read a Redis hash and coerce every value to an integer count.
     *
     * @return array<string, int>
     */
    private function readHash(string $key): array
    {
        /** @var array<string, string> $hash */
        $hash = $this->connection()->hgetall($key);

        return array_map(intval(...), $hash);
    }

    /**
     * Build a namespaced key for a bucket and category.
     */
    private function key(string $bucket, string $category): string
    {
        return sprintf('au:b:%s:%s', $bucket, $category);
    }

    /**
     * The phpredis connection that holds the counters. This store relies on the phpredis client (pipelining with a
     * typed callback), so a non-phpredis connection is a misconfiguration.
     */
    private function connection(): PhpRedisConnection
    {
        $connection = Redis::connection($this->connection);

        throw_unless($connection instanceof PhpRedisConnection, RuntimeException::class, 'The API usage store requires a phpredis Redis connection.');

        return $connection;
    }
}
