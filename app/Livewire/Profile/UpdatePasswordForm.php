<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Actions\Fortify\PasswordValidationRules;
use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Livewire\Component;

class UpdatePasswordForm extends Component
{
    use PasswordValidationRules;

    /**
     * The component's state.
     *
     * @var array<string, string>
     */
    public array $state = [
        'current_password' => '',
        'password' => '',
        'password_confirmation' => '',
    ];

    /**
     * Update the user's password.
     *
     * This method allows a user that has a null password to set a password for their account
     * without needing to provide their current password. This is useful for users that have
     * been created using OAuth.
     */
    public function updatePassword(UpdatesUserPasswords $updatesUserPasswords): void
    {
        $this->resetErrorBag();

        $user = Auth::user();

        if ($user->password !== null) {
            // User has a password, use the standard update process
            $updatesUserPasswords->update($user, $this->state);

            if (request()->hasSession()) {
                request()->session()->put([
                    'password_hash_'.Auth::getDefaultDriver() => $user->getAuthPassword(),
                ]);
            }

            $this->state = [
                'current_password' => '',
                'password' => '',
                'password_confirmation' => '',
            ];

            Track::event(TrackingEventType::PASSWORD_CHANGE);

            $this->dispatch('saved');
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

    /**
     * Get the current user of the application.
     */
    public function getUserProperty(): User
    {
        return Auth::user();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('profile.update-password-form');
    }
}
