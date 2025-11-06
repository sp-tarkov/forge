<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mod;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

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

        // Check if mod is published and not scheduled for future
        if (! $mod->published_at || $mod->published_at->isFuture()) {
            $isPrivilegedUser = $user && ($mod->isAuthorOrOwner($user) || $user->isModOrAdmin());
            if (! $isPrivilegedUser) {
                return false;
            }
        }

        // For non-privileged users, check if mod is publicly visible
        $isPrivilegedUser = $user && ($mod->isAuthorOrOwner($user) || $user->isModOrAdmin());
        if (! $isPrivilegedUser && ! $mod->isPubliclyVisible()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        return auth()->check() && $user->hasMfaEnabled()
            ? Response::allow()
            : Response::deny(__('Your account must have MFA enabled to create a new mod.'));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Mod $mod): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isModOrAdmin() || $mod->isAuthorOrOwner($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Mod $mod): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

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
     * Determine whether the user can download the mod.
     * Blocked users can still download mods.
     */
    public function download(?User $user, Mod $mod): bool
    {
        // Check if mod can be viewed first
        if (! $this->view($user, $mod)) {
            return false;
        }

        // Allow downloads even if blocked
        return true;
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
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can enable the model.
     */
    public function enable(User $user, Mod $mod): bool
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
    public function unpublish(User $user, Mod $mod): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $mod->isAuthorOrOwner($user);
    }

    /**
     * Determine whether the user can publish the model.
     */
    public function publish(User $user, Mod $mod): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $mod->isAuthorOrOwner($user);
    }

    /**
     * Determine whether the user can disable the model.
     */
    public function feature(User $user, Mod $mod): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        // Cannot feature mods that contain AI content
        if ($mod->contains_ai_content) {
            return false;
        }

        return $user->isAdmin();
    }

    /**
     * Determine whether the user can enable the model.
     */
    public function unfeature(User $user, Mod $mod): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view actions for the model. Example: Edit, Delete, etc.
     */
    public function viewActions(User $user, Mod $mod): bool
    {
        return $mod->isAuthorOrOwner($user);
    }

    /**
     * Determine whether the user can report a mod.
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
}
