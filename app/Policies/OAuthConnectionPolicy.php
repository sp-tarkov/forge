<?php

namespace App\Policies;

use App\Models\OAuthConnection;
use App\Models\User;

class OAuthConnectionPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OAuthConnection $oauthConnection): bool
    {
        return $user->id === $oauthConnection->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OAuthConnection $oauthConnection): bool
    {
        return $user->id === $oauthConnection->user_id && $user->password !== null;
    }
}
