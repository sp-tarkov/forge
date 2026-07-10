<?php

declare(strict_types=1);

use App\Enums\ImageVariantFit;
use App\Services\ThumbnailService;
use App\Support\DataTransferObjects\ImageCropRect;
use Illuminate\Support\Facades\Storage;

function makeThumbnailTestImage(int $width, int $height, string $format = 'png'): string
{
    $image = new Imagick;
    $image->newImage($width, $height, new ImagickPixel('red'));
    $image->setImageFormat($format);

    $blob = $image->getImageBlob();
    $image->clear();

    return $blob;
}

beforeEach(function (): void {
    Storage::fake('public');
    $this->service = new ThumbnailService;
});

it('generates a webp variant for each configured width', function (): void {
    Storage::disk('public')->put('mods/source.png', makeThumbnailTestImage(512, 512));

    $variants = $this->service->generateVariants('public', 'mods/source.png');

    expect($variants)->toBe([
        192 => 'mods/source_192w.webp',
        384 => 'mods/source_384w.webp',
    ]);

    foreach ($variants as $width => $path) {
        Storage::disk('public')->assertExists($path);

        $image = new Imagick;
        $image->readImageBlob((string) Storage::disk('public')->get($path));
        expect(mb_strtolower($image->getImageFormat()))->toBe('webp')
            ->and($image->getImageWidth())->toBe($width)
            ->and($image->getImageHeight())->toBe($width);
        $image->clear();
    }
});

it('crops non-square sources to square variants', function (): void {
    Storage::disk('public')->put('mods/wide.png', makeThumbnailTestImage(800, 400));

    $variants = $this->service->generateVariants('public', 'mods/wide.png');

    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($variants[192]));
    expect($image->getImageWidth())->toBe(192)
        ->and($image->getImageHeight())->toBe(192);
    $image->clear();
});

it('skips variants that would upscale the source image', function (): void {
    Storage::disk('public')->put('mods/small.png', makeThumbnailTestImage(256, 256));

    $variants = $this->service->generateVariants('public', 'mods/small.png');

    expect($variants)->toBe([192 => 'mods/small_192w.webp']);
    Storage::disk('public')->assertMissing('mods/small_384w.webp');
});

it('returns no variants when the source is smaller than every configured width', function (): void {
    Storage::disk('public')->put('mods/tiny.png', makeThumbnailTestImage(100, 100));

    expect($this->service->generateVariants('public', 'mods/tiny.png'))->toBe([]);
});

it('returns no variants when the source file does not exist', function (): void {
    expect($this->service->generateVariants('public', 'mods/missing.png'))->toBe([]);
});

it('returns no variants when the source file is not a valid image', function (): void {
    Storage::disk('public')->put('mods/corrupt.png', 'not-an-image');

    expect($this->service->generateVariants('public', 'mods/corrupt.png'))->toBe([]);
});

it('deletes variant files from storage', function (): void {
    Storage::disk('public')->put('mods/source_192w.webp', 'variant-content');
    Storage::disk('public')->put('mods/source_384w.webp', 'variant-content');

    $this->service->deleteVariants('public', [
        192 => 'mods/source_192w.webp',
        384 => 'mods/source_384w.webp',
    ]);

    Storage::disk('public')->assertMissing('mods/source_192w.webp');
    Storage::disk('public')->assertMissing('mods/source_384w.webp');
});

it('handles null variants when deleting', function (): void {
    $this->service->deleteVariants('public', null);
})->throwsNoExceptions();

it('generates variants at custom widths', function (): void {
    Storage::disk('public')->put('profile-photos/avatar.png', makeThumbnailTestImage(512, 512));

    $variants = $this->service->generateVariants('public', 'profile-photos/avatar.png', ThumbnailService::AVATAR_WIDTHS);

    expect($variants)->toBe([
        128 => 'profile-photos/avatar_128w.webp',
        256 => 'profile-photos/avatar_256w.webp',
    ]);
});

it('generates aspect-preserving variants with the width fit', function (): void {
    Storage::disk('public')->put('cover-photos/banner.png', makeThumbnailTestImage(3000, 1500));

    $variants = $this->service->generateVariants(
        'public',
        'cover-photos/banner.png',
        ThumbnailService::COVER_WIDTHS,
        ImageVariantFit::Width,
    );

    expect($variants)->toBe([
        1280 => 'cover-photos/banner_1280w.webp',
        2560 => 'cover-photos/banner_2560w.webp',
    ]);

    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($variants[1280]));
    expect(mb_strtolower($image->getImageFormat()))->toBe('webp')
        ->and($image->getImageWidth())->toBe(1280)
        ->and($image->getImageHeight())->toBe(640);
    $image->clear();
});

it('skips width-fit variants that would upscale the source', function (): void {
    Storage::disk('public')->put('cover-photos/narrow.png', makeThumbnailTestImage(800, 2000));

    $variants = $this->service->generateVariants(
        'public',
        'cover-photos/narrow.png',
        ThumbnailService::COVER_WIDTHS,
        ImageVariantFit::Width,
    );

    expect($variants)->toBe([]);
});

it('generates animated webp variants when animation is preserved', function (): void {
    Storage::disk('public')->put('profile-photos/animated.gif', makeAnimatedTestImage(4, 512, 512));

    $variants = $this->service->generateVariants(
        'public',
        'profile-photos/animated.gif',
        ThumbnailService::AVATAR_WIDTHS,
        preserveAnimation: true,
    );

    expect($variants)->toBe([
        128 => 'profile-photos/animated_128w.webp',
        256 => 'profile-photos/animated_256w.webp',
    ]);

    foreach ($variants as $width => $path) {
        $image = new Imagick;
        $image->readImageBlob((string) Storage::disk('public')->get($path));
        expect(mb_strtolower($image->getImageFormat()))->toBe('webp')
            ->and($image->getNumberImages())->toBe(4)
            ->and($image->getImageWidth())->toBe($width)
            ->and($image->getImageHeight())->toBe($width);
        $image->clear();
    }
});

it('flattens animations over the frame cap to static variants', function (): void {
    Storage::disk('public')->put('profile-photos/long.gif', makeAnimatedTestImage(121, 200, 200));

    $variants = $this->service->generateVariants(
        'public',
        'profile-photos/long.gif',
        ThumbnailService::AVATAR_WIDTHS,
        preserveAnimation: true,
    );

    expect($variants)->toHaveKey(128);

    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($variants[128]));

    expect($image->getNumberImages())->toBe(1);
    $image->clear();
});

it('flattens animations over the pixel budget to static variants', function (): void {
    $animation = new Imagick;
    $first = new Imagick;
    $first->newImage(2500, 2500, new ImagickPixel('red'));
    $first->setImageFormat('gif');

    $animation->addImage($first);
    $first->clear();

    for ($i = 0; $i < 5; $i++) {
        $frame = new Imagick;
        $frame->newImage(1, 1, new ImagickPixel('blue'));
        $frame->setImageFormat('gif');
        $animation->addImage($frame);
        $animation->setImagePage(2500, 2500, 0, 0);
        $frame->clear();
    }

    $animation->setFormat('gif');
    Storage::disk('public')->put('profile-photos/heavy.gif', $animation->getImagesBlob());
    $animation->clear();

    $variants = $this->service->generateVariants(
        'public',
        'profile-photos/heavy.gif',
        ThumbnailService::AVATAR_WIDTHS,
        preserveAnimation: true,
    );

    expect($variants)->toHaveKey(128);

    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($variants[128]));

    expect($image->getNumberImages())->toBe(1);
    $image->clear();
});

describe('normalizeAvatar', function (): void {
    it('crops every frame to the rect and encodes an animated webp', function (): void {
        $blob = $this->service->normalizeAvatar(
            makeAnimatedTestImage(3, 400, 300),
            new ImageCropRect(50, 20, 200, 250),
        );

        $image = new Imagick;
        $image->readImageBlob((string) $blob);
        expect(mb_strtolower($image->getImageFormat()))->toBe('webp')
            ->and($image->getNumberImages())->toBe(3)
            ->and($image->getImageWidth())->toBe(200)
            ->and($image->getImageHeight())->toBe(200);
        $image->clear();
    });

    it('clamps a rect that overflows the image bounds', function (): void {
        $blob = $this->service->normalizeAvatar(
            makeAnimatedTestImage(2, 300, 300),
            new ImageCropRect(250, 250, 200, 200),
        );

        $image = new Imagick;
        $image->readImageBlob((string) $blob);
        expect($image->getImageWidth())->toBe(50)
            ->and($image->getImageHeight())->toBe(50);
        $image->clear();
    });

    it('center-crops the largest square without a rect', function (): void {
        $blob = $this->service->normalizeAvatar(makeAnimatedTestImage(2, 400, 300));

        $image = new Imagick;
        $image->readImageBlob((string) $blob);
        expect($image->getNumberImages())->toBe(2)
            ->and($image->getImageWidth())->toBe(300)
            ->and($image->getImageHeight())->toBe(300);
        $image->clear();
    });

    it('produces a static webp from a static source with a rect applied', function (): void {
        $blob = $this->service->normalizeAvatar(
            makeThumbnailTestImage(400, 300),
            new ImageCropRect(0, 0, 150, 150),
        );

        $image = new Imagick;
        $image->readImageBlob((string) $blob);
        expect(mb_strtolower($image->getImageFormat()))->toBe('webp')
            ->and($image->getNumberImages())->toBe(1)
            ->and($image->getImageWidth())->toBe(150);
        $image->clear();
    });

    it('flattens an animation over the frame cap to a static webp', function (): void {
        $blob = $this->service->normalizeAvatar(makeAnimatedTestImage(121, 64, 64));

        $image = new Imagick;
        $image->readImageBlob((string) $blob);
        expect($image->getNumberImages())->toBe(1)
            ->and($image->getImageWidth())->toBe(64);
        $image->clear();
    });

    it('returns null for undecodable content', function (): void {
        expect($this->service->normalizeAvatar('not-an-image'))->toBeNull();
    });
});

it('flattens animated gif sources to single-frame webp variants', function (): void {
    $animation = new Imagick;
    foreach (['red', 'blue'] as $color) {
        $frame = new Imagick;
        $frame->newImage(512, 512, new ImagickPixel($color));
        $frame->setImageFormat('gif');
        $frame->setImageDelay(10);
        $animation->addImage($frame);
        $frame->clear();
    }

    $animation->setFormat('gif');
    Storage::disk('public')->put('mods/animated.gif', $animation->getImagesBlob());
    $animation->clear();

    $variants = $this->service->generateVariants('public', 'mods/animated.gif');

    expect($variants)->toBe([
        192 => 'mods/animated_192w.webp',
        384 => 'mods/animated_384w.webp',
    ]);

    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($variants[192]));
    expect(mb_strtolower($image->getImageFormat()))->toBe('webp')
        ->and($image->getNumberImages())->toBe(1)
        ->and($image->getImageWidth())->toBe(192)
        ->and($image->getImageHeight())->toBe(192);
    $image->clear();
});
