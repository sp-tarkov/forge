<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\UserBlocked;
use App\Events\UserUnblocked;
use App\Jobs\CleanupBlockedNotificationsJob;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class UserBlockingService
{
    /**
     * Block a user and handle all related cleanup.
     */
    public function blockUser(User $blocker, User $blocked, ?string $reason = null): UserBlock
    {
        $userBlock = DB::transaction(fn (): UserBlock => $blocker->block($blocked, $reason));

        dispatch(new CleanupBlockedNotificationsJob($blocker, $blocked));

        $this->broadcastSafely(new UserBlocked($blocker, $blocked));

        return $userBlock;
    }

    /**
     * Unblock a user.
     */
    public function unblockUser(User $blocker, User $blocked): bool
    {
        $removed = DB::transaction(fn (): bool => $blocker->unblock($blocked));

        if ($removed) {
            $this->broadcastSafely(new UserUnblocked($blocker, $blocked));
        }

        return $removed;
    }

    /**
     * Broadcast an event without letting a websocket outage bubble up as a request failure.
     */
    private function broadcastSafely(ShouldBroadcast $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (BroadcastException $broadcastException) {
            Log::warning('Block broadcast failed; realtime update skipped.', [
                'event' => $event::class,
                'exception' => $broadcastException->getMessage(),
            ]);
        }
    }
}
