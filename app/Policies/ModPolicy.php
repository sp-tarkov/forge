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
        // Only mods/admins can view disabled mods. Everyone else is denied immediately.
        if ($mod->disabled) {
            return $user?->isModOrAdmin() ?? false;
        }

        if (! $this->hasValidSptVersion($mod)) {
            $isPrivilegedUser = $user && ($this->isAuthorOrOwner($user, $mod) || $user->isModOrAdmin());
            if (! $isPrivilegedUser) {
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
        // TODO: check MFA
        return auth()->check();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Mod $mod): bool
    {
        return $user->isModOrAdmin() || $mod->authors->contains($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Mod $mod): bool
    {
        return $user->isAdmin() || $mod->owner->id === $user->id;
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

    /**
     * Determine whether the user can disable the model.
     */
    public function feature(User $user, Mod $mod): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can enable the model.
     */
    public function unfeature(User $user, Mod $mod): bool
    {
        return $user->isAdmin();
    }

    /**
     * Check if a version has a valid SPT version tag.
     */
    private function hasValidSptVersion(Mod $mod): bool
    {
        $mod->loadMissing(['versions.latestSptVersion']);

        return $mod->versions->contains(fn ($version): bool => ! is_null($version->latestSptVersion));
    }

    /**
     * Check if the user is an author or the owner of the mod.
     */
    private function isAuthorOrOwner(?User $user, Mod $mod): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->id === $mod->owner?->id || $mod->authors->pluck('id')->contains($user->id);
    }
}
