<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(?User $userCurrent, User $userResource): bool
    {
        // TODO: check to see if either of the users have blocked each other.
        return true;
    }

    /*
     * Determine whether the user can view disabled objects. This is only allowed for the user themselves and
     * moderators and administrators.
     */
    public function viewDisabled(?User $user, User $userResource): bool
    {
        return $user && ($user->isModOrAdmin() || $user->id === $userResource->id);
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
}
