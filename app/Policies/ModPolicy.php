<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mod;
use App\Models\User;

class ModPolicy
{
    /**
     * Determine whether the user can view multiple models.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific model.
     */
    public function view(?User $user, Mod $mod): bool
    {
        // Disabled mods will not be shown to normal users.
        if ($mod->disabled && ! $user?->isModOrAdmin()) {
            return false;
        }

        // Only allow authors, admins, and mods to view mods without an SPT version tag.
        if ($user && ($mod->users->pluck('id')->doesntContain($user->id) && ! $user->isModOrAdmin())) {
            $hasValidVersion = $mod->versions->first(fn ($version): bool => ! is_null($version->latestSptVersion));
            if (! $hasValidVersion) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Mod $mod): bool
    {
        return $user->isModOrAdmin() || $mod->users->contains($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Mod $mod): bool
    {
        return $user->isAdmin() || $mod->users->contains($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Mod $mod): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Mod $mod): bool
    {
        return false;
    }

    /**
     * Determine whether the user can disable the model.
     */
    public function disable(User $user, Mod $mod): bool
    {
        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can enable the model.
     */
    public function enable(User $user, Mod $mod): bool
    {
        return $user->isModOrAdmin();
    }
}
