<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

/**
 * A user-chosen crop rectangle expressed in natural-pixel coordinates of the source image.
 */
final readonly class ImageCropRect
{
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {}

    /**
     * Build a rect from a validated request array, returning null when any key is missing, non-integer, has a
     * negative origin, or has a non-positive dimension.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $values = [];
        foreach (['x', 'y', 'width', 'height'] as $key) {
            $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);
            if ($value === false) {
                return null;
            }

            $values[$key] = $value;
        }

        if ($values['x'] < 0 || $values['y'] < 0 || $values['width'] < 1 || $values['height'] < 1) {
            return null;
        }

        return new self($values['x'], $values['y'], $values['width'], $values['height']);
    }

    /**
     * Build a centered rect covering the largest square of an image with the given dimensions.
     */
    public static function centeredSquare(int $imageWidth, int $imageHeight): self
    {
        $side = min($imageWidth, $imageHeight);

        return new self(
            intdiv($imageWidth - $side, 2),
            intdiv($imageHeight - $side, 2),
            $side,
            $side,
        );
    }

    /**
     * Clamp the rect to the image bounds and square it via the smallest usable side, returning null when no usable
     * area remains inside the image.
     */
    public function clampToSquare(int $imageWidth, int $imageHeight): ?self
    {
        $x = max(0, min($this->x, $imageWidth - 1));
        $y = max(0, min($this->y, $imageHeight - 1));
        $side = min($this->width, $this->height, $imageWidth - $x, $imageHeight - $y);

        if ($side < 1) {
            return null;
        }

        return new self($x, $y, $side, $side);
    }
}
