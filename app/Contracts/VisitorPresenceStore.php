<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Tracks which visitors are currently active so the footer can show a live "users online" count without WebSockets.
 *
 * Presence is recorded from normal page activity (one entry per distinct visitor, keyed by a stable token) and read
 * back as a count of entries seen within a sliding window. The production implementation is backed by Redis sorted
 * sets (shared across every Octane worker and app server); an in-memory implementation is used where Redis is
 * unavailable (notably the test suite). Presence is ephemeral by design: losing it only blanks the live count for a
 * moment until normal traffic repopulates it.
 */
interface VisitorPresenceStore
{
    /**
     * Record that a visitor is active right now.
     *
     * @param  string  $token  A stable per-visitor identifier (`u:{id}` for members, `g:{hash}` for guests).
     * @param  bool  $isMember  Whether the visitor is an authenticated user (also counted toward the member total).
     */
    public function record(string $token, bool $isMember): void;

    /**
     * Count the visitors active within the current window.
     *
     * @return array{total: int, members: int}
     */
    public function counts(): array;
}
