<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\DB;

final class UserBlockingService
{
    /**
     * Block a user and handle all related cleanup.
     */
    public function blockUser(User $blocker, User $blocked, ?string $reason = null): UserBlock
    {
        return DB::transaction(fn (): UserBlock => $blocker->block($blocked, $reason));
    }

    /**
     * Unblock a user.
     */
    public function unblockUser(User $blocker, User $blocked): bool
    {
        return DB::transaction(fn (): bool => $blocker->unblock($blocked));
    }
}
