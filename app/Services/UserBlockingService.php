<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\DB;

class UserBlockingService
{
    /**
     * Block a user and handle all related cleanup.
     */
    public function blockUser(User $blocker, User $blocked, ?string $reason = null): UserBlock
    {
        return DB::transaction(function () use ($blocker, $blocked, $reason): UserBlock {
            $block = $blocker->block($blocked, $reason);
            $this->clearUserCaches($blocker, $blocked);

            return $block;
        });
    }

    /**
     * Unblock a user.
     */
    public function unblockUser(User $blocker, User $blocked): bool
    {
        return DB::transaction(function () use ($blocker, $blocked): bool {
            $result = $blocker->unblock($blocked);
            if ($result) {
                $this->clearUserCaches($blocker, $blocked);
            }

            return $result;
        });
    }

    /**
     * Clear caches for both users.
     */
    protected function clearUserCaches(User $userOne, User $userTwo): void
    {
        cache()->forget(sprintf('user_%d_role_name', $userOne->id));
        cache()->forget(sprintf('user_%d_role_name', $userTwo->id));
    }
}
