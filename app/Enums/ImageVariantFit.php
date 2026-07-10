<?php

declare(strict_types=1);

namespace App\Enums;

use Imagick;

/**
 * Represents how an image variant is fitted to its target width.
 */
enum ImageVariantFit: string
{
    /**
     * The image is center-cropped to a square of the target width.
     */
    case SquareCrop = 'square_crop';

    /**
     * The image is resized to the target width, preserving its aspect ratio.
     */
    case Width = 'width';

    /**
     * Whether a variant at the target width can be generated from the source dimensions without upscaling.
     */
    public function canGenerate(int $sourceWidth, int $sourceHeight, int $targetWidth): bool
    {
        return match ($this) {
            self::SquareCrop => min($sourceWidth, $sourceHeight) >= $targetWidth,
            self::Width => $sourceWidth >= $targetWidth,
        };
    }

    /**
     * Resize the image in place to the target width using this fit.
     */
    public function apply(Imagick $image, int $targetWidth): void
    {
        match ($this) {
            self::SquareCrop => $image->cropThumbnailImage($targetWidth, $targetWidth),
            self::Width => $image->thumbnailImage($targetWidth, 0),
        };
    }
}
