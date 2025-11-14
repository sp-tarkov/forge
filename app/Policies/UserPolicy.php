<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(?User $userCurrent, User $userResource): bool
    {
        // Allow viewing if not logged in
        if ($userCurrent === null) {
            return true;
        }

        // Prevent viewing only if the profile owner has blocked the viewer
        // The blocker can still view the blocked user's profile
        if ($userResource->hasBlocked($userCurrent)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can view disabled mods on a user page. This is only allowed for the user that owns the
     * user page, moderators, and administrators.
     */
    public function viewDisabledUserMods(?User $user, User $userPageOwner): bool
    {
        if ($user->isModOrAdmin()) {
            return true;
        }

        if ($user->id === $userPageOwner->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view disabled addons on a user page. This is allowed for moderators,
     * administrators, the profile owner, and any authors of addons on the profile.
     */
    public function viewDisabledUserAddons(?User $user, User $userPageOwner): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isModOrAdmin()) {
            return true;
        }

        if ($user->id === $userPageOwner->id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can report a user.
     *
     * Authentication and email verification are required.
     */
    public function report(User $user, Model $reportable): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        // Moderators and administrators cannot create reports.
        if ($user->isModOrAdmin()) {
            return false;
        }

        // Check if the reportable model has the required method.
        if (! method_exists($reportable, 'hasBeenReportedBy')) {
            return false;
        }

        // User cannot report the same item more than once.
        return ! $reportable->hasBeenReportedBy($user->id);
    }

    /**
     * Determine whether the user can ban another user.
     *
     * Only administrators can ban users, with the following restrictions:
     * - Cannot ban other administrators
     * - Cannot ban themselves
     */
    public function ban(User $user, User $targetUser): bool
    {
        // Only administrators can ban users
        if (! $user->isAdmin()) {
            return false;
        }

        // Cannot ban other administrators
        if ($targetUser->isAdmin()) {
            return false;
        }

        // Cannot ban yourself
        if ($user->id === $targetUser->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can unban another user.
     *
     * Uses the same authorization logic as ban since unbanning requires the same administrative privileges.
     */
    public function unban(User $user, User $targetUser): bool
    {
        return $this->ban($user, $targetUser);
    }
}
