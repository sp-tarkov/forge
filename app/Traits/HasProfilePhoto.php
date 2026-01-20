<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HasProfilePhoto
{
    /**
     * Update the user's profile photo.
     */
    public function updateProfilePhoto(UploadedFile $uploadedFile, string $storagePath = 'profile-photos'): void
    {
        tap($this->profile_photo_path, function ($previous) use ($uploadedFile, $storagePath): void {
            $this->forceFill([
                'profile_photo_path' => $uploadedFile->storePublicly(
                    $storagePath, ['disk' => $this->profilePhotoDisk()]
                ),
            ])->save();

            if ($previous) {
                Storage::disk($this->profilePhotoDisk())->delete($previous);
            }
        });
    }

    /**
     * Delete the user's profile photo.
     */
    public function deleteProfilePhoto(): void
    {
        if (is_null($this->profile_photo_path)) {
            return;
        }

        Storage::disk($this->profilePhotoDisk())->delete($this->profile_photo_path);

        $this->forceFill([
            'profile_photo_path' => null,
        ])->save();
    }

    /**
     * Get the disk that profile photos should be stored on.
     */
    protected function profilePhotoDisk(): string
    {
        return config('filesystems.asset_upload', 'public');
    }

    /**
     * Get the profile photo URL for the user.
     *
     * @return Attribute<string, never>
     */
    protected function profilePhotoUrl(): Attribute
    {
        return new Attribute(
            get: fn (): string => $this->profile_photo_path
                ? Storage::disk($this->profilePhotoDisk())->url($this->profile_photo_path)
                : $this->defaultProfilePhotoUrl()
        );
    }

    /**
     * Get the default profile photo URL if no profile photo has been uploaded.
     */
    protected function defaultProfilePhotoUrl(): string
    {
        $name = mb_trim(collect(explode(' ', $this->name))->map(fn ($segment): string => mb_substr((string) $segment, 0, 1))->join(' '));

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
    }
}
