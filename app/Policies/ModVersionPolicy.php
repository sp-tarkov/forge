<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModVersionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any mod versions.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the mod version.
     */
    public function view(?User $user, ModVersion $modVersion): bool
    {
        if ($user?->isModOrAdmin()) {
            return true;
        }

        if ($modVersion->disabled) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create mod versions.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the mod version.
     */
    public function update(User $user, ModVersion $modVersion): bool
    {
        return $user->isModOrAdmin() || $modVersion->mod->users->contains($user);
    }

    /**
     * Determine whether the user can delete the mod version.
     */
    public function delete(User $user, ModVersion $modVersion): bool
    {
        return $user->isAdmin() || $modVersion->mod->users->contains($user);
    }

    /**
     * Determine whether the user can restore the mod version.
     */
    public function restore(User $user, ModVersion $modVersion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the mod version.
     */
    public function forceDelete(User $user, ModVersion $modVersion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can download the mod version.
     */
    public function download(?User $user, ModVersion $modVersion): bool
    {
        if ($user?->isModOrAdmin()) {
            return true;
        }

        if ($modVersion->mod->disabled || $modVersion->disabled) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can disable the model.
     */
    public function disable(User $user, ModVersion $modVersion): bool
    {
        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can enable the model.
     */
    public function enable(User $user, ModVersion $modVersion): bool
    {
        return $user->isModOrAdmin();
    }
}
