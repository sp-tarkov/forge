<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\ThumbnailService;

/**
 * Represents a user-owned image field that has resized variants.
 */
enum UserImageType: string
{
    /**
     * The user's avatar, displayed as a square.
     */
    case ProfilePhoto = 'profile_photo';

    /**
     * The user's profile banner, displayed full-width.
     */
    case CoverPhoto = 'cover_photo';

    /**
     * Get the users table column holding the image's storage path.
     */
    public function pathColumn(): string
    {
        return match ($this) {
            self::ProfilePhoto => 'profile_photo_path',
            self::CoverPhoto => 'cover_photo_path',
        };
    }

    /**
     * Get the users table column holding the image's variant paths.
     */
    public function variantsColumn(): string
    {
        return match ($this) {
            self::ProfilePhoto => 'profile_photo_variants',
            self::CoverPhoto => 'cover_photo_variants',
        };
    }

    /**
     * Get the pixel widths that variants are generated at.
     *
     * @return list<int>
     */
    public function widths(): array
    {
        return match ($this) {
            self::ProfilePhoto => ThumbnailService::AVATAR_WIDTHS,
            self::CoverPhoto => ThumbnailService::COVER_WIDTHS,
        };
    }

    /**
     * Get the fit used when generating variants.
     */
    public function fit(): ImageVariantFit
    {
        return match ($this) {
            self::ProfilePhoto => ImageVariantFit::SquareCrop,
            self::CoverPhoto => ImageVariantFit::Width,
        };
    }

    /**
     * Whether variants keep the animation frames of an animated source.
     */
    public function preservesAnimation(): bool
    {
        return match ($this) {
            self::ProfilePhoto => true,
            self::CoverPhoto => false,
        };
    }
}
