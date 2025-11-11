<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AddonVersionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any addon versions.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the addon version.
     */
    public function view(?User $user, AddonVersion $addonVersion): bool
    {
        if ($user?->isModOrAdmin()) {
            return true;
        }

        if ($addonVersion->disabled) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create addon versions.
     */
    public function create(User $user, Addon $addon): bool
    {
        return $addon->isAuthorOrOwner($user) && $user->hasMfaEnabled();
    }

    /**
     * Determine whether the user can update the addon version.
     */
    public function update(User $user, AddonVersion $addonVersion): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isModOrAdmin() || $addonVersion->addon->isAuthorOrOwner($user);
    }

    /**
     * Determine whether the user can delete the addon version.
     */
    public function delete(User $user, AddonVersion $addonVersion): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isAdmin() || $addonVersion->addon->owner_id === $user->id;
    }

    /**
     * Determine whether the user can restore the addon version.
     */
    public function restore(User $user, AddonVersion $addonVersion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the addon version.
     */
    public function forceDelete(User $user, AddonVersion $addonVersion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can download the addon version.
     */
    public function download(?User $user, AddonVersion $addonVersion): bool
    {
        if ($user?->isModOrAdmin()) {
            return true;
        }

        if ($addonVersion->addon->disabled || $addonVersion->disabled) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can disable the model.
     */
    public function disable(User $user, AddonVersion $addonVersion): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can enable the model.
     */
    public function enable(User $user, AddonVersion $addonVersion): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can unpublish the model.
     */
    public function unpublish(User $user, AddonVersion $addonVersion): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $addonVersion->addon->isAuthorOrOwner($user);
    }

    /**
     * Determine whether the user can publish the model.
     */
    public function publish(User $user, AddonVersion $addonVersion): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $addonVersion->addon->isAuthorOrOwner($user);
    }
}
