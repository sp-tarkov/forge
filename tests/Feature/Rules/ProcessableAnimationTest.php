<?php

declare(strict_types=1);

use App\Rules\ProcessableAnimation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Handcraft a GIF whose header claims the given dimensions and frame count without carrying real pixel data, so
 * cap checks can be exercised without allocating huge images.
 */
function makeGifHeaderTestBlob(int $width, int $height, int $frames): string
{
    $gif = 'GIF89a'.pack('v', $width).pack('v', $height)."\x70\x00\x00";

    for ($i = 0; $i < $frames; $i++) {
        $gif .= "\x21\xF9\x04\x00\x05\x00\x00\x00";
        $gif .= "\x2C".pack('v', 0).pack('v', 0).pack('v', $width).pack('v', $height)."\x00";
        $gif .= "\x02\x02\x44\x01\x00";
    }

    return $gif."\x3B";
}

function validateProcessableAnimation(UploadedFile $file): array
{
    $validator = Validator::make(['photo' => $file], ['photo' => [new ProcessableAnimation]]);

    return $validator->errors()->get('photo');
}

it('passes a static image', function (): void {
    $image = new Imagick;
    $image->newImage(256, 256, new ImagickPixel('teal'));
    $image->setImageFormat('png');

    $file = UploadedFile::fake()->createWithContent('avatar.png', $image->getImageBlob());
    $image->clear();

    expect(validateProcessableAnimation($file))->toBe([]);
});

it('passes a small animation', function (): void {
    $file = UploadedFile::fake()->createWithContent('avatar.gif', makeAnimatedTestImage(3, 64, 64));

    expect(validateProcessableAnimation($file))->toBe([]);
});

it('rejects an animation with too many frames', function (): void {
    $file = UploadedFile::fake()->createWithContent('avatar.gif', makeGifHeaderTestBlob(16, 16, 121));

    expect(validateProcessableAnimation($file))->toHaveCount(1)
        ->and(validateProcessableAnimation($file)[0])->toContain('at most 120 frames');
});

it('rejects an animation over the coalesced pixel budget', function (): void {
    $file = UploadedFile::fake()->createWithContent('avatar.gif', makeGifHeaderTestBlob(4000, 4000, 2));

    expect(validateProcessableAnimation($file)[0])->toContain('animation is too large');
});

it('rejects an image over the decode ceiling', function (): void {
    $file = UploadedFile::fake()->createWithContent('avatar.gif', makeGifHeaderTestBlob(60000, 60000, 1));

    expect(validateProcessableAnimation($file)[0])->toContain('image is too large');
});

it('passes non-image content through for the mimes rule to reject', function (): void {
    $file = UploadedFile::fake()->createWithContent('avatar.txt', 'plain text content');

    expect(validateProcessableAnimation($file))->toBe([]);
});
