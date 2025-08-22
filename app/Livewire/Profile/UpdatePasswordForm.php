<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Actions\Fortify\PasswordValidationRules;
use App\Enums\TrackingEventType;
use App\Facades\Track;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Jetstream\Http\Livewire\UpdatePasswordForm as JetstreamUpdatePasswordForm;
use Override;

class UpdatePasswordForm extends JetstreamUpdatePasswordForm
{
    use PasswordValidationRules;

    /**
     * Update the user's password.
     *
     * This method has been overwritten to allow a user that has a null password to set a password for their account
     * without needing to provide their current password. This is useful for users that have been created using OAuth.
     */
    #[Override]
    public function updatePassword(UpdatesUserPasswords $updatesUserPasswords): void
    {
        $this->resetErrorBag();

        $user = Auth::user();

        if ($user->password !== null) {
            parent::updatePassword($updatesUserPasswords);
            Track::event(TrackingEventType::PASSWORD_CHANGE);
        } else {

            // User has a null password. Allow them to set a new password without their current password.
            Validator::make($this->state, [
                'password' => $this->passwordRules(),
            ])->validateWithBag('updatePassword');

            auth()->user()->forceFill([
                'password' => Hash::make($this->state['password']),
            ])->save();

            $this->state = [
                'current_password' => '',
                'password' => '',
                'password_confirmation' => '',
            ];

            Track::event(TrackingEventType::PASSWORD_CHANGE);

            $this->dispatch('saved');
        }
    }
}
