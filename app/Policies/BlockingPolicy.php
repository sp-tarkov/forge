<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class BlockingPolicy
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
            return Response::deny('You cannot block administrators.');
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

    /**
     * Determine if the user can view the blocked users list.
     */
    public function viewBlockedUsers(User $user): bool
    {
        return true; // All users can view their own blocked list
    }

    /**
     * Determine if the user can interact with another user.
     */
    public function canInteract(User $user, User $target): bool
    {
        // Cannot interact if there is mutual blocking
        return ! $user->isBlockedMutually($target);
    }

    /**
     * Determine if the user can view another user's profile.
     */
    public function viewProfile(User $user, User $target): bool
    {
        // Cannot view if blocked
        return ! $user->isBlockedMutually($target);
    }

    /**
     * Determine if the user can send messages to another user.
     */
    public function sendMessage(User $user, User $target): bool
    {
        // Cannot message if blocked
        return ! $user->isBlockedMutually($target);
    }

    /**
     * Determine if the user can comment on content owned by another user.
     */
    public function commentOnContent(User $user, User $owner): bool
    {
        // Cannot comment if blocked
        return ! $user->isBlockedMutually($owner);
    }
}
