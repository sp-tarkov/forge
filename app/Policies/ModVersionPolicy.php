<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

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
    public function create(User $user, Mod $mod): Response
    {
        if ($user->id !== $mod->owner_id && $mod->authors->doesntContain($user)) {
            return Response::deny(__('You must be the owner or an author of this mod to create a version.'));
        }

        if (! $user->hasMfaEnabled()) {
            return Response::deny(__('Your account must have MFA enabled to create a new mod version.'));
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the mod version.
     */
    public function update(User $user, ModVersion $modVersion): bool
    {
        return $user->isModOrAdmin() || $modVersion->mod->authors->contains($user);
    }

    /**
     * Determine whether the user can delete the mod version.
     */
    public function delete(User $user, ModVersion $modVersion): bool
    {
        return $user->isAdmin() || $modVersion->mod->owner->id === $user->id;
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

    /**
     * Determine whether the user can view actions for the model. Example: Edit, Delete, etc.
     */
    public function viewActions(User $user, ModVersion $modVersion): bool
    {
        return $this->isAuthorOrOwner($user, $modVersion);
    }

    /**
     * Check if the user is an author or the owner of the mod.
     */
    private function isAuthorOrOwner(?User $user, ModVersion $modVersion): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->id === $modVersion->mod->owner?->id || $modVersion->mod->authors->pluck('id')->contains($user->id);
    }
}
