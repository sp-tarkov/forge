<?php

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
        // Everyone can view mods, unless they are disabled.
        if ($mod->disabled && ! $user?->isModOrAdmin()) {
            return false;
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
        return $user->isMod() || $user->isAdmin() || $mod->users->contains($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Mod $mod): bool
    {
        // I'm guessing we want the mod author to also be able to do this?
        // what if there are multiple authors?
        // I'm leaving that out for now -waffle.lazy
        return $user->isAdmin();
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
}
