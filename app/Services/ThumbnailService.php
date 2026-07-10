<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ImageVariantFit;
use App\Support\DataTransferObjects\ImageCropRect;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickException;

final class ThumbnailService
{
    /**
     * The square pixel widths that thumbnail variants are generated at.
     *
     * @var list<int>
     */
    public const array VARIANT_WIDTHS = [192, 384];

    /**
     * The square pixel widths that user avatar variants are generated at.
     *
     * @var list<int>
     */
    public const array AVATAR_WIDTHS = [128, 256];

    /**
     * The pixel widths that user cover photo variants are generated at, preserving aspect ratio.
     *
     * @var list<int>
     */
    public const array COVER_WIDTHS = [1280, 2560];

    /**
     * The maximum frame count processed as an animation. Sources above it are flattened to their first frame.
     */
    public const int MAX_ANIMATION_FRAMES = 120;

    /**
     * The maximum coalesced pixel budget (frames x canvas width x canvas height) processed as an animation.
     * Sources above it are flattened to their first frame.
     */
    public const int MAX_ANIMATION_PIXELS = 30_000_000;

    /**
     * The maximum total pinged pixels (summed across frames) tolerated for any full decode. Sources above it are
     * treated as undecodable.
     */
    public const int MAX_DECODE_PIXELS = 60_000_000;

    /**
     * Generate WebP variants of the source image at the given widths using the given fit, keyed by pixel width.
     * Variants that would require upscaling the source image are skipped. When animation is preserved, every frame
     * of an animated source within the animation caps is transformed and the variants are animated WebP files.
     *
     * @param  list<int>  $widths
     * @return array<int, string>
     */
    public function generateVariants(
        string $disk,
        string $sourcePath,
        array $widths = self::VARIANT_WIDTHS,
        ImageVariantFit $fit = ImageVariantFit::SquareCrop,
        bool $preserveAnimation = false,
    ): array {
        $storage = Storage::disk($disk);

        if (! $storage->exists($sourcePath)) {
            return [];
        }

        $contents = $storage->get($sourcePath);
        if ($contents === null) {
            return [];
        }

        $source = $this->readFrames($contents, $preserveAnimation);
        if (! $source instanceof Imagick) {
            return [];
        }

        $source->setIteratorIndex(0);
        $sourceWidth = $source->getImageWidth();
        $sourceHeight = $source->getImageHeight();

        $variants = [];

        foreach ($widths as $width) {
            if (! $fit->canGenerate($sourceWidth, $sourceHeight, $width)) {
                continue;
            }

            $variant = clone $source;
            foreach ($variant as $frame) {
                $fit->apply($frame, $width);
                $frame->setImagePage($frame->getImageWidth(), $frame->getImageHeight(), 0, 0);
            }

            $blob = $this->encodeWebp($variant, 80);
            $variant->clear();
            if ($blob === null) {
                continue;
            }

            $variantPath = $this->variantPath($sourcePath, $width);
            $storage->put($variantPath, $blob, 'public');

            $variants[$width] = $variantPath;
        }

        $source->clear();

        return $variants;
    }

    /**
     * Crop every frame of the image to a square rect and re-encode it as WebP quality 90, preserving animation
     * within the animation caps. The rect is clamped to the image bounds; without a usable rect the largest
     * centered square is used. Returns null when the blob cannot be decoded safely.
     */
    public function normalizeAvatar(string $blob, ?ImageCropRect $cropRect = null): ?string
    {
        $frames = $this->readFrames($blob, true);
        if (! $frames instanceof Imagick) {
            return null;
        }

        $frames->setIteratorIndex(0);
        $imageWidth = $frames->getImageWidth();
        $imageHeight = $frames->getImageHeight();

        $rect = $cropRect?->clampToSquare($imageWidth, $imageHeight)
            ?? ImageCropRect::centeredSquare($imageWidth, $imageHeight);

        foreach ($frames as $frame) {
            $frame->cropImage($rect->width, $rect->height, $rect->x, $rect->y);
            $frame->setImagePage($rect->width, $rect->height, 0, 0);
        }

        $encoded = $this->encodeWebp($frames, 90);
        $frames->clear();

        return $encoded;
    }

    /**
     * Delete the given variant files from storage.
     *
     * @param  array<int|string, string>|null  $variants
     */
    public function deleteVariants(string $disk, ?array $variants): void
    {
        foreach ($variants ?? [] as $variantPath) {
            Storage::disk($disk)->delete($variantPath);
        }
    }

    /**
     * Decode the blob within the safety caps. Returns coalesced frames for an animation within the animation caps
     * when animation is preserved, a single detached first frame otherwise, or null when the blob is unreadable or
     * exceeds the decode ceiling.
     */
    private function readFrames(string $blob, bool $preserveAnimation): ?Imagick
    {
        try {
            $ping = new Imagick;
            $ping->pingImageBlob($blob);

            $frameCount = $ping->getNumberImages();
            $totalPixels = 0;
            foreach ($ping as $pingFrame) {
                $totalPixels += $pingFrame->getImageWidth() * $pingFrame->getImageHeight();
            }

            $ping->setIteratorIndex(0);
            $page = $ping->getImagePage();
            $canvasWidth = max($page['width'], $ping->getImageWidth());
            $canvasHeight = max($page['height'], $ping->getImageHeight());
            $ping->clear();
        } catch (ImagickException) {
            return null;
        }

        if ($totalPixels > self::MAX_DECODE_PIXELS) {
            return null;
        }

        try {
            $source = new Imagick;
            $source->readImageBlob($blob);
        } catch (ImagickException) {
            return null;
        }

        $withinAnimationCaps = $frameCount > 1
            && $frameCount <= self::MAX_ANIMATION_FRAMES
            && $frameCount * $canvasWidth * $canvasHeight <= self::MAX_ANIMATION_PIXELS;

        if ($preserveAnimation && $withinAnimationCaps) {
            try {
                $coalesced = $source->coalesceImages();
                $source->clear();

                return $coalesced;
            } catch (ImagickException) {
                // The static first frame below is used instead.
            }
        }

        if ($source->getNumberImages() > 1) {
            $source->setIteratorIndex(0);
            $frame = $source->getImage();
            $source->clear();

            return $frame;
        }

        return $source;
    }

    /**
     * Re-encode the frames as WebP at the given quality, producing an animated blob for multi-frame input and
     * retrying with the first frame alone when the animated encode fails. Returns null when nothing encodes.
     */
    private function encodeWebp(Imagick $frames, int $quality): ?string
    {
        try {
            foreach ($frames as $frame) {
                $frame->setImageFormat('webp');
                $frame->setImageCompressionQuality($quality);
                $frame->stripImage();
            }

            $frames->setIteratorIndex(0);

            return $frames->getNumberImages() > 1 ? $frames->getImagesBlob() : $frames->getImageBlob();
        } catch (ImagickException) {
            try {
                $frames->setIteratorIndex(0);

                return $frames->getImageBlob();
            } catch (ImagickException) {
                return null;
            }
        }
    }

    /**
     * Build the storage path for a variant of the source image.
     */
    private function variantPath(string $sourcePath, int $width): string
    {
        $directory = pathinfo($sourcePath, PATHINFO_DIRNAME);
        $stem = pathinfo($sourcePath, PATHINFO_FILENAME);
        $prefix = in_array($directory, ['.', ''], true) ? '' : $directory.'/';

        return sprintf('%s%s_%dw.webp', $prefix, $stem, $width);
    }
}
