<?php

declare(strict_types=1);

use App\Livewire\Concerns\RendersMarkdownPreview;
use App\Models\User;
use App\Rules\ProcessableAnimation;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use RendersMarkdownPreview;
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
     * The crop rectangle for an animated avatar upload, in natural pixels of the source image.
     *
     * @var array{x: int, y: int, width: int, height: int}|null
     */
    public ?array $photoCropRect = null;

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
            'photo' => ['mimes:jpg,jpeg,png,webp,gif,avif', 'max:1024', 'dimensions:min_width=128,min_height=128', new ProcessableAnimation], // 1MB Max
        ]);
    }

    /**
     * When the cover is temporarily uploaded.
     */
    public function updatedCover(): void
    {
        $this->validate([
            'cover' => 'mimes:jpg,jpeg,png,webp,gif,avif|max:2048', // 2MB Max
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function updateProfileInformation(UpdatesUserProfileInformation $updater): void
    {
        $this->resetErrorBag();

        /** @var User $user */
        $user = Auth::user();

        $updater->update(
            $user,
            $this->photo || $this->cover
                ? array_merge($this->state, array_filter([
                    'photo' => $this->photo,
                    'photoCropRect' => $this->photoCropRect,
                    'cover' => $this->cover,
                ])) : $this->state
        );

        $this->photo = null;
        $this->photoCropRect = null;
        $this->cover = null;

        Flux::toast(heading: 'Profile Updated', text: 'Your profile has been updated successfully.', variant: 'success');

        $this->dispatch('refresh-navigation-menu');
    }

    /**
     * Clear the pending profile photo upload.
     */
    public function removePhoto(): void
    {
        $this->photo = null;
        $this->photoCropRect = null;
        $this->resetErrorBag('photo');
    }

    /**
     * Clear the pending cover photo upload.
     */
    public function removeCover(): void
    {
        $this->cover = null;
        $this->resetErrorBag('cover');
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
