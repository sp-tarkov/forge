<?php

declare(strict_types=1);

namespace App\Support\Visitors;

use App\Contracts\VisitorPresenceStore;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Redis;
use Redis as PhpRedis;
use RuntimeException;

/**
 * Redis-backed {@see VisitorPresenceStore}.
 *
 * Each visitor is one member of a sorted set scored by their last-seen Unix timestamp: `presence:visitors` holds
 * everyone and `presence:members` holds authenticated users. Recording is a single `ZADD` (idempotent per token, so
 * reloads and navigation never inflate the count); reading is a `ZCOUNT` over the live window, which stays exact even
 * when expired entries have not been pruned yet. Stale entries are trimmed at most once a minute, gated by a short
 * cache lock, so the sets stay bounded without a scheduled job.
 */
final readonly class RedisVisitorPresenceStore implements VisitorPresenceStore
{
    /**
     * The sorted set holding every active visitor, scored by last-seen Unix timestamp.
     */
    private const string VISITORS_KEY = 'presence:visitors';

    /**
     * The sorted set holding active authenticated users, scored by last-seen Unix timestamp.
     */
    private const string MEMBERS_KEY = 'presence:members';

    /**
     * The cache key whose presence gates pruning to at most once per minute across all workers.
     */
    private const string PRUNE_LOCK_KEY = 'presence:prune-lock';

    public function __construct(
        private string $connection,
        private int $windowSeconds,
    ) {}

    public function record(string $token, bool $isMember): void
    {
        $now = Date::now()->getTimestamp();

        $this->connection()->pipeline(function (PhpRedis $pipe) use ($token, $isMember, $now): void {
            $pipe->zadd(self::VISITORS_KEY, $now, $token);

            if ($isMember) {
                $pipe->zadd(self::MEMBERS_KEY, $now, $token);
            }
        });

        $this->pruneExpired($now);
    }

    public function counts(): array
    {
        $threshold = (string) (Date::now()->getTimestamp() - $this->windowSeconds);
        $connection = $this->connection();

        // Two cheap ZCOUNT reads (this result is cached upstream, so it runs about once per window, not per render).
        return [
            'total' => (int) ($connection->zCount(self::VISITORS_KEY, $threshold, '+inf')),
            'members' => (int) ($connection->zCount(self::MEMBERS_KEY, $threshold, '+inf')),
        ];
    }

    /**
     * Trim entries that have fallen outside the window, at most once per minute across all workers.
     */
    private function pruneExpired(int $now): void
    {
        if (! Cache::add(self::PRUNE_LOCK_KEY, true, 60)) {
            return;
        }

        // Exclusive max: remove only entries strictly older than the window start, leaving the live window intact.
        $cutoff = '('.($now - $this->windowSeconds);

        $this->connection()->pipeline(function (PhpRedis $pipe) use ($cutoff): void {
            $pipe->zremrangebyscore(self::VISITORS_KEY, '-inf', $cutoff);
            $pipe->zremrangebyscore(self::MEMBERS_KEY, '-inf', $cutoff);
        });
    }

    /**
     * The phpredis connection that holds the presence sets. This store relies on the phpredis client (pipelining with
     * a typed callback), so a non-phpredis connection is a misconfiguration.
     */
    private function connection(): PhpRedisConnection
    {
        $connection = Redis::connection($this->connection);

        throw_unless($connection instanceof PhpRedisConnection, RuntimeException::class, 'The visitor presence store requires a phpredis Redis connection.');

        return $connection;
    }
}
