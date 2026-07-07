<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickException;

final class ThumbnailService
{
    /**
     * The square pixel widths that variants are generated at.
     *
     * @var list<int>
     */
    public const array VARIANT_WIDTHS = [192, 384];

    /**
     * Generate square WebP variants of the source image, keyed by pixel width. Variants larger than the source
     * image's smallest dimension are skipped so images are never upscaled.
     *
     * @return array<int, string>
     */
    public function generateVariants(string $disk, string $sourcePath): array
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($sourcePath)) {
            return [];
        }

        $contents = $storage->get($sourcePath);
        if ($contents === null) {
            return [];
        }

        try {
            $source = new Imagick;
            $source->readImageBlob($contents);
            $source->setIteratorIndex(0);
        } catch (ImagickException) {
            return [];
        }

        $variants = [];

        foreach (self::VARIANT_WIDTHS as $width) {
            if (min($source->getImageWidth(), $source->getImageHeight()) < $width) {
                continue;
            }

            $variant = clone $source;
            $variant->cropThumbnailImage($width, $width);
            $variant->setImageFormat('webp');
            $variant->setImageCompressionQuality(80);
            $variant->stripImage();

            $variantPath = $this->variantPath($sourcePath, $width);
            $storage->put($variantPath, $variant->getImageBlob(), 'public');
            $variant->clear();

            $variants[$width] = $variantPath;
        }

        $source->clear();

        return $variants;
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
