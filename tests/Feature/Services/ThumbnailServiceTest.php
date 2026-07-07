<?php

declare(strict_types=1);

use App\Services\ThumbnailService;
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
