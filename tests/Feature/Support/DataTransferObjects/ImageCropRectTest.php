<?php

declare(strict_types=1);

use App\Support\DataTransferObjects\ImageCropRect;

describe('fromArray', function (): void {
    it('builds a rect from a complete integer array', function (): void {
        $rect = ImageCropRect::fromArray(['x' => 10, 'y' => 20, 'width' => 300, 'height' => 400]);

        expect($rect)->not->toBeNull()
            ->and($rect->x)->toBe(10)
            ->and($rect->y)->toBe(20)
            ->and($rect->width)->toBe(300)
            ->and($rect->height)->toBe(400);
    });

    it('accepts numeric strings', function (): void {
        $rect = ImageCropRect::fromArray(['x' => '5', 'y' => '6', 'width' => '128', 'height' => '128']);

        expect($rect)->not->toBeNull()
            ->and($rect->x)->toBe(5)
            ->and($rect->width)->toBe(128);
    });

    it('returns null when a key is missing', function (): void {
        expect(ImageCropRect::fromArray(['x' => 0, 'y' => 0, 'width' => 100]))->toBeNull();
    });

    it('returns null for non-integer values', function (): void {
        expect(ImageCropRect::fromArray(['x' => 'abc', 'y' => 0, 'width' => 100, 'height' => 100]))->toBeNull();
    });

    it('returns null for negative origins', function (): void {
        expect(ImageCropRect::fromArray(['x' => -1, 'y' => 0, 'width' => 100, 'height' => 100]))->toBeNull();
    });

    it('returns null for non-positive dimensions', function (): void {
        expect(ImageCropRect::fromArray(['x' => 0, 'y' => 0, 'width' => 0, 'height' => 100]))->toBeNull();
    });
});

describe('centeredSquare', function (): void {
    it('centers on a wide image', function (): void {
        $rect = ImageCropRect::centeredSquare(800, 400);

        expect($rect->x)->toBe(200)
            ->and($rect->y)->toBe(0)
            ->and($rect->width)->toBe(400)
            ->and($rect->height)->toBe(400);
    });

    it('centers on a tall image', function (): void {
        $rect = ImageCropRect::centeredSquare(300, 900);

        expect($rect->x)->toBe(0)
            ->and($rect->y)->toBe(300)
            ->and($rect->width)->toBe(300)
            ->and($rect->height)->toBe(300);
    });
});

describe('clampToSquare', function (): void {
    it('squares a non-square rect via the smallest side', function (): void {
        $rect = new ImageCropRect(10, 10, 200, 300)->clampToSquare(1000, 1000);

        expect($rect->width)->toBe(200)
            ->and($rect->height)->toBe(200)
            ->and($rect->x)->toBe(10)
            ->and($rect->y)->toBe(10);
    });

    it('shrinks the side at the image edges', function (): void {
        $rect = new ImageCropRect(350, 0, 200, 200)->clampToSquare(400, 400);

        expect($rect->x)->toBe(350)
            ->and($rect->width)->toBe(50)
            ->and($rect->height)->toBe(50);
    });

    it('clamps an out-of-bounds origin back inside the image', function (): void {
        $rect = new ImageCropRect(500, 500, 100, 100)->clampToSquare(400, 400);

        expect($rect->x)->toBe(399)
            ->and($rect->y)->toBe(399)
            ->and($rect->width)->toBe(1);
    });

    it('returns null when no usable area remains', function (): void {
        expect(new ImageCropRect(0, 0, 100, 100)->clampToSquare(0, 0))->toBeNull();
    });
});
