<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Features\SupportRedirects\Redirector;
use Override;

class UpdateProfileForm extends UpdateProfileInformationForm
{
    /**
     * The new cover photo for the user.
     */
    public $cover;

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
    #[Override]
    public function updateProfileInformation(UpdatesUserProfileInformation $updatesUserProfileInformation): RedirectResponse|Redirector|null
    {
        $this->resetErrorBag();

        $updatesUserProfileInformation->update(
            Auth::user(),
            $this->photo || $this->cover
                ? array_merge($this->state, array_filter([
                    'photo' => $this->photo,
                    'cover' => $this->cover,
                ])) : $this->state
        );

        if ($this->photo !== null || $this->cover !== null) {
            return redirect()->route('profile.show');
        }

        $this->dispatch('saved');

        $this->dispatch('refresh-navigation-menu');

        return null;
    }

    /**
     * Delete user's profile photo.
     */
    public function deleteCoverPhoto(): void
    {
        Auth::user()->deleteCoverPhoto();

        $this->dispatch('refresh-navigation-menu');
    }
}
