<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

final class BlockingPolicy
{
    /**
     * Determine if the user can block another user.
     */
    public function block(User $user, User $target): Response
    {
        // Cannot block yourself
        if ($user->id === $target->id) {
            return Response::deny('You cannot block yourself.');
        }

        // Cannot block if already blocked
        if ($user->hasBlocked($target)) {
            return Response::deny('You have already blocked this user.');
        }

        // Cannot block admins
        if ($target->isAdmin()) {
            return Response::deny('You cannot block staff members.');
        }

        // Cannot block senior moderators
        if ($target->isSeniorMod()) {
            return Response::deny('You cannot block senior moderators.');
        }

        // Cannot block moderators
        if ($target->isMod()) {
            return Response::deny('You cannot block moderators.');
        }

        return Response::allow();
    }

    /**
     * Determine if the user can unblock another user.
     */
    public function unblock(User $user, User $target): Response
    {
        // Must have blocked the user to unblock them
        if (! $user->hasBlocked($target)) {
            return Response::deny('You have not blocked this user.');
        }

        return Response::allow();
    }
}
