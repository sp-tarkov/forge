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

        /** @var User $user */
        $user = Auth::user();

        if ($user->password !== null) {
            $updatesUserPasswords->update($user, $this->state);

            if (request()->hasSession()) {
                request()->session()->put([
                    'password_hash_'.Auth::getDefaultDriver() => $user->getAuthPassword(),
                ]);
            }
        } else {
            $this->validate(
                ['state.password' => $this->passwordRules()],
                [],
                ['state.password' => __('password')],
            );

            $user->forceFill([
                'password' => Hash::make($this->state['password']),
            ])->save();
        }

        $this->resetState();

        Track::event(TrackingEventType::PASSWORD_CHANGE);

        $this->dispatch('saved');
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

    /**
     * Reset the form state to empty values.
     */
    private function resetState(): void
    {
        $this->state = [
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ];
    }
}
