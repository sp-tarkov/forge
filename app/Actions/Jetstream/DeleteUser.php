<?php

declare(strict_types=1);

namespace App\Actions\Jetstream;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\User;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        // Store user information before deletion for tracking purposes
        $userData = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        Track::event(TrackingEventType::ACCOUNT_DELETE, $user, $userData);

        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();
    }
}
