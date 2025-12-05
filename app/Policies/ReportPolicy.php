<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Report $report): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isModOrAdmin() || $user->id === $report->reporter_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return ! $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Report $report): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Report $report): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Report $report): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Report $report): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isAdmin();
    }

    /**
     * Determine whether the user can unresolve the model.
     */
    public function unresolve(User $user, Report $report): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isAdmin();
    }
}
