<?php

declare(strict_types=1);

namespace App\Support\Visitors;

use App\Contracts\VisitorPresenceStore;
use Illuminate\Support\Facades\Date;

/**
 * In-memory {@see VisitorPresenceStore} used where Redis is not available (notably the test suite).
 *
 * State lives on the instance, so this must be bound as a singleton to accumulate presence across the requests of a
 * single test. It mirrors the Redis store's window semantics (a visitor counts while their last-seen timestamp is
 * within the window, honouring `Date::now()` so `Carbon::setTestNow()` controls it) and is deliberately simple and
 * not safe across processes.
 */
final class ArrayVisitorPresenceStore implements VisitorPresenceStore
{
    /**
     * Last-seen Unix timestamp per visitor token.
     *
     * @var array<string, int>
     */
    private array $visitors = [];

    /**
     * Last-seen Unix timestamp per authenticated visitor token.
     *
     * @var array<string, int>
     */
    private array $members = [];

    public function __construct(private readonly int $windowSeconds) {}

    public function record(string $token, bool $isMember): void
    {
        $now = Date::now()->getTimestamp();

        $this->visitors[$token] = $now;

        if ($isMember) {
            $this->members[$token] = $now;
        }
    }

    public function counts(): array
    {
        $threshold = Date::now()->getTimestamp() - $this->windowSeconds;

        return [
            'total' => $this->countWithin($this->visitors, $threshold),
            'members' => $this->countWithin($this->members, $threshold),
        ];
    }

    /**
     * Count tokens whose last-seen timestamp is at or after the threshold.
     *
     * @param  array<string, int>  $set
     */
    private function countWithin(array $set, int $threshold): int
    {
        return count(array_filter($set, static fn (int $seenAt): bool => $seenAt >= $threshold));
    }
}
