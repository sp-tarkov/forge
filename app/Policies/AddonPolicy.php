<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class AddonPolicy
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
    public function view(?User $user, Addon $addon): bool
    {
        // Only mods/admins can view disabled addons
        if ($addon->disabled) {
            return $user?->isModOrAdmin() ?? false;
        }

        // For non-privileged users, check if addon is publicly visible
        $isPrivilegedUser = $user && ($addon->isAuthorOrOwner($user) || $user->isModOrAdmin());
        if (! $isPrivilegedUser && ! $addon->isPubliclyVisible()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Mod $mod): Response
    {
        // Must have MFA enabled
        if (! $user->hasMfaEnabled()) {
            return Response::deny(__('Your account must have MFA enabled to create a new addon.'));
        }

        // Mod must allow addons
        if (! $mod->addons_enabled) {
            return Response::deny(__('This mod does not allow addons.'));
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Addon $addon): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isModOrAdmin() || $addon->isAuthorOrOwner($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Addon $addon): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isAdmin() || $addon->owner_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Addon $addon): bool
    {
        return false;
    }

    /**
     * Determine whether the user can download the addon.
     * Blocked users can still download addons.
     */
    public function download(?User $user, Addon $addon): bool
    {
        // Check if addon can be viewed first
        if (! $this->view($user, $addon)) {
            return false;
        }

        // Allow downloads even if blocked
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Addon $addon): bool
    {
        return false;
    }

    /**
     * Determine whether the user can disable the model.
     */
    public function disable(User $user, Addon $addon): bool
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
    public function enable(User $user, Addon $addon): bool
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
    public function unpublish(User $user, Addon $addon): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $addon->isAuthorOrOwner($user);
    }

    /**
     * Determine whether the user can publish the model.
     */
    public function publish(User $user, Addon $addon): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $addon->isAuthorOrOwner($user);
    }

    /**
     * Determine whether the user can attach addon to its parent mod.
     */
    public function attach(User $user, Addon $addon): Response
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return Response::deny(__('You must verify your email address.'));
        }

        // Addon must be detached
        if (! $addon->isDetached()) {
            return Response::deny(__('This addon is not detached.'));
        }

        // Addon must have a parent mod
        if (! $addon->mod) {
            return Response::deny(__('This addon does not have a parent mod.'));
        }

        // User must be owner/author of parent mod, moderator, or admin
        if (! ($user->isModOrAdmin() || $addon->mod->isAuthorOrOwner($user))) {
            return Response::deny(__('You must be an owner or author of the parent mod, or a moderator/administrator to attach this addon.'));
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can detach addon from its parent mod.
     */
    public function detach(User $user, Addon $addon): Response
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return Response::deny(__('You must verify your email address.'));
        }

        // Addon must not already be detached
        if ($addon->isDetached()) {
            return Response::deny(__('This addon is already detached.'));
        }

        // Addon must have a parent mod
        if (! $addon->mod) {
            return Response::deny(__('This addon does not have a parent mod.'));
        }

        // User must be owner/author of parent mod, moderator, or admin
        if (! ($user->isModOrAdmin() || $addon->mod->isAuthorOrOwner($user))) {
            return Response::deny(__('You must be an owner or author of the parent mod, or a moderator/administrator to detach this addon.'));
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can view actions for the model. Example: Edit, Delete, etc.
     */
    public function viewActions(User $user, Addon $addon): bool
    {
        return $addon->isAuthorOrOwner($user);
    }

    /**
     * Determine whether the user can report an addon.
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
