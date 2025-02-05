<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HasCoverPhoto
{
    /**
     * Update the user's cover photo.
     */
    public function updateCoverPhoto(UploadedFile $uploadedFile, string $storagePath = 'cover-photos'): void
    {
        tap($this->cover_photo_path, function ($previous) use ($uploadedFile, $storagePath): void {
            $this->forceFill([
                'cover_photo_path' => $uploadedFile->storePublicly(
                    $storagePath, ['disk' => $this->coverPhotoDisk()]
                ),
            ])->save();

            if ($previous) {
                Storage::disk($this->coverPhotoDisk())->delete($previous);
            }
        });
    }

    /**
     * Get the disk that cover photos should be stored on.
     */
    protected function coverPhotoDisk(): string
    {
        return config('filesystems.asset_upload', 'public');
    }

    /**
     * Delete the user's cover photo.
     */
    public function deleteCoverPhoto(): void
    {
        if (is_null($this->cover_photo_path)) {
            return;
        }

        Storage::disk($this->coverPhotoDisk())->delete($this->cover_photo_path);

        $this->forceFill([
            'cover_photo_path' => null,
        ])->save();
    }

    /**
     * Get the cover photo URL for the user.
     *
     * @return Attribute<string[], never>
     */
    protected function coverPhotoUrl(): Attribute
    {
        return new Attribute(
            get: fn (): string => $this->cover_photo_path
                ? Storage::disk($this->coverPhotoDisk())->url($this->cover_photo_path)
                : $this->defaultCoverPhotoUrl()
        );
    }

    /**
     * Get the default profile photo URL if no profile photo has been uploaded.
     */
    protected function defaultCoverPhotoUrl(): string
    {
        return 'https://picsum.photos/seed/'.urlencode($this->name).'/720/100?blur=2';
    }
}
