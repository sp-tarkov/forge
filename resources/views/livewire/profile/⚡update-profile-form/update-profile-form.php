<?php

declare(strict_types=1);

use App\Models\User;
use Flux\Flux;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    /**
     * The component's state.
     *
     * @var array<string, mixed>
     */
    public array $state = [];

    /**
     * The new avatar for the user.
     */
    public mixed $photo = null;

    /**
     * The new cover photo for the user.
     */
    public mixed $cover = null;

    /**
     * Determine if the verification email was sent.
     */
    public bool $verificationLinkSent = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var array<string, mixed> $userArray */
        $userArray = $user->withoutRelations()->toArray();

        $this->state = array_merge([
            'email' => $user->email,
            'about' => $user->about ?? '',
        ], $userArray);
    }

    /**
     * When the photo is temporarily uploaded.
     */
    public function updatedPhoto(): void
    {
        $this->validate([
            'photo' => 'image|mimes:jpg,jpeg,png|max:1024', // 1MB Max
        ]);
    }

    /**
     * When the cover is temporarily uploaded.
     */
    public function updatedCover(): void
    {
        $this->validate([
            'cover' => 'image|mimes:jpg,jpeg,png|max:2048', // 2MB Max
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function updateProfileInformation(UpdatesUserProfileInformation $updater): ?RedirectResponse
    {
        $this->resetErrorBag();

        /** @var User $user */
        $user = Auth::user();

        $updater->update(
            $user,
            $this->photo || $this->cover
                ? array_merge($this->state, array_filter([
                    'photo' => $this->photo,
                    'cover' => $this->cover,
                ])) : $this->state
        );

        if ($this->photo !== null || $this->cover !== null) {
            return to_route('profile.show');
        }

        Flux::toast(text: 'Profile updated.');

        $this->dispatch('refresh-navigation-menu');

        return null;
    }

    /**
     * Delete user's profile photo.
     */
    public function deleteProfilePhoto(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $user->deleteProfilePhoto();

        $this->dispatch('refresh-navigation-menu');
    }

    /**
     * Delete user's cover photo.
     */
    public function deleteCoverPhoto(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $user->deleteCoverPhoto();

        $this->dispatch('refresh-navigation-menu');
    }

    /**
     * Send the email verification.
     */
    public function sendEmailVerification(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $user->sendEmailVerificationNotification();

        $this->verificationLinkSent = true;
    }

    /**
     * Get the current user of the application.
     */
    public function getUserProperty(): User
    {
        /** @var User */
        return Auth::user();
    }
};
