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
     * Get the disk that cover photos should be stored on.
     */
    protected function coverPhotoDisk(): string
    {
        return config()->string('filesystems.asset_upload', 'public');
    }

    /**
     * Get the cover photo URL for the user, or null when no cover photo has been uploaded.
     *
     * @return Attribute<string|null, never>
     */
    protected function coverPhotoUrl(): Attribute
    {
        /** @var Attribute<string|null, never> $attribute */
        $attribute = new Attribute(
            get: fn (): ?string => $this->cover_photo_path
                ? Storage::disk($this->coverPhotoDisk())->url($this->cover_photo_path)
                : null
        );

        return $attribute;
    }

    /**
     * Get the CSS gradient used as the cover photo placeholder when no cover photo has been uploaded. The gradient
     * colors are derived deterministically from the user's name so each profile gets a stable, distinct banner without
     * relying on any external image service.
     *
     * @return Attribute<string, never>
     */
    protected function coverPhotoGradient(): Attribute
    {
        /** @var Attribute<string, never> $attribute */
        $attribute = new Attribute(
            get: function (): string {
                $hue = crc32((string) $this->name) % 360;
                $secondHue = ($hue + 50) % 360;

                return sprintf(
                    'linear-gradient(135deg, hsl(%d, 65%%, 55%%) 0%%, hsl(%d, 65%%, 45%%) 100%%)',
                    $hue,
                    $secondHue,
                );
            }
        );

        return $attribute;
    }
}
