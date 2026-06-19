<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    /**
     * Indicates if user deletion is being confirmed.
     */
    public bool $confirmingUserDeletion = false;

    /**
     * The user's current password.
     */
    public string $password = '';

    /**
     * Confirm that the user would like to delete their account.
     */
    public function confirmUserDeletion(): void
    {
        $this->resetErrorBag();

        $this->password = '';

        $this->dispatch('confirming-delete-user');

        $this->confirmingUserDeletion = true;
    }

    /**
     * Delete the current user.
     */
    public function deleteUser(): void
    {
        $this->resetErrorBag();

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($this->password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('This password does not match our records.')],
            ]);
        }

        // Get fresh user from database to ensure we have the actual model
        /** @var User $freshUser */
        $freshUser = User::query()->find($user->id);

        // Store user information before deletion for tracking purposes
        $userData = [
            'name' => $freshUser->name,
            'email' => $freshUser->email,
        ];

        Track::event(TrackingEventType::ACCOUNT_DELETE, $freshUser, $userData);

        $freshUser->deleteProfilePhoto();
        $freshUser->delete();

        Auth::guard('web')->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        $this->redirect(config('fortify.redirects.logout', '/'));
    }
};
