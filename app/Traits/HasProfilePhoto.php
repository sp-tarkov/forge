<?php

declare(strict_types=1);

namespace App\Traits;

use App\Jobs\NormalizeUserAvatar;
use App\Services\ThumbnailService;
use App\Support\DataTransferObjects\ImageCropRect;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HasProfilePhoto
{
    /**
     * Update the user's profile photo, remove the previous photo and its variants, and queue normalization of the
     * raw upload (square crop across every frame, WebP re-encode, and variant generation).
     */
    public function updateProfilePhoto(
        UploadedFile $uploadedFile,
        ?ImageCropRect $cropRect = null,
        string $storagePath = 'profile-photos',
    ): void {
        $previousPath = $this->profile_photo_path;
        $previousVariants = $this->profile_photo_variants;

        $rawPath = $uploadedFile->storePublicly($storagePath, ['disk' => $this->profilePhotoDisk()]);
        if ($rawPath === false) {
            return;
        }

        $this->forceFill([
            'profile_photo_path' => $rawPath,
            'profile_photo_variants' => null,
        ])->save();

        if ($previousPath) {
            Storage::disk($this->profilePhotoDisk())->delete($previousPath);
        }

        resolve(ThumbnailService::class)->deleteVariants($this->profilePhotoDisk(), $previousVariants);

        dispatch(new NormalizeUserAvatar($this, $rawPath, $cropRect));
    }

    /**
     * Delete the user's profile photo and its variants.
     */
    public function deleteProfilePhoto(): void
    {
        if (is_null($this->profile_photo_path)) {
            return;
        }

        Storage::disk($this->profilePhotoDisk())->delete($this->profile_photo_path);
        resolve(ThumbnailService::class)->deleteVariants($this->profilePhotoDisk(), $this->profile_photo_variants);

        $this->forceFill([
            'profile_photo_path' => null,
            'profile_photo_variants' => null,
        ])->save();
    }

    /**
     * Get the disk that profile photos should be stored on.
     */
    protected function profilePhotoDisk(): string
    {
        return config()->string('filesystems.asset_upload', 'public');
    }

    /**
     * Get the profile photo URL for the user, preferring the largest resized variant.
     *
     * @return Attribute<string, never>
     */
    protected function profilePhotoUrl(): Attribute
    {
        /** @var Attribute<string, never> $attribute */
        $attribute = new Attribute(
            get: function (): string {
                $variants = $this->profile_photo_variants ?? [];
                if ($variants !== []) {
                    return Storage::disk($this->profilePhotoDisk())->url($variants[max(array_keys($variants))]);
                }

                return $this->profile_photo_path
                    ? Storage::disk($this->profilePhotoDisk())->url($this->profile_photo_path)
                    : $this->defaultProfilePhotoUrl();
            }
        );

        return $attribute;
    }

    /**
     * Build the srcset attribute value for the user's profile photo variants.
     *
     * @return Attribute<string, never>
     */
    protected function profilePhotoSrcset(): Attribute
    {
        /** @var Attribute<string, never> $attribute */
        $attribute = new Attribute(
            get: fn (): string => collect($this->profile_photo_variants ?? [])
                ->map(fn (string $path, int|string $width): string => sprintf('%s %dw', Storage::disk($this->profilePhotoDisk())->url($path), $width))
                ->implode(', ')
        );

        return $attribute;
    }

    /**
     * Get the default profile photo URL if no profile photo has been uploaded.
     */
    protected function defaultProfilePhotoUrl(): string
    {
        $name = mb_trim(collect(explode(' ', $this->name ?? ''))->map(fn ($segment): string => mb_substr($segment, 0, 1))->join(' '));

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
    }
}
