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
        Track::event(TrackingEventType::ACCOUNT_DELETE);

        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();
    }
}
