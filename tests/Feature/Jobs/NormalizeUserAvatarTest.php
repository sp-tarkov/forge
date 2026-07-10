<?php

declare(strict_types=1);

use App\Jobs\NormalizeUserAvatar;
use App\Models\User;
use App\Services\ThumbnailService;
use App\Support\DataTransferObjects\ImageCropRect;
use Illuminate\Support\Facades\Storage;

function makeNormalizeTestImage(int $width, int $height): string
{
    $image = new Imagick;
    $image->newImage($width, $height, new ImagickPixel('teal'));
    $image->setImageFormat('png');

    $blob = $image->getImageBlob();
    $image->clear();

    return $blob;
}

beforeEach(function (): void {
    Storage::fake('public');
});

it('normalizes the raw upload to a new webp with variants and removes the raw file', function (): void {
    Storage::disk('public')->put('profile-photos/raw.png', makeNormalizeTestImage(512, 512));
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/raw.png']);

    new NormalizeUserAvatar($user, 'profile-photos/raw.png')->handle(resolve(ThumbnailService::class));

    $user->refresh();
    expect($user->profile_photo_path)->not->toBe('profile-photos/raw.png')
        ->and($user->profile_photo_path)->toStartWith('profile-photos/')
        ->and($user->profile_photo_path)->toEndWith('.webp')
        ->and($user->profile_photo_variants)->toHaveKeys([128, 256]);

    Storage::disk('public')->assertMissing('profile-photos/raw.png');
    Storage::disk('public')->assertExists($user->profile_photo_path);

    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($user->profile_photo_path));
    expect(mb_strtolower($image->getImageFormat()))->toBe('webp')
        ->and($image->getImageWidth())->toBe(512)
        ->and($image->getImageHeight())->toBe(512);
    $image->clear();
});

it('applies the crop rect to the normalized image', function (): void {
    Storage::disk('public')->put('profile-photos/raw.png', makeNormalizeTestImage(400, 300));
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/raw.png']);

    new NormalizeUserAvatar($user, 'profile-photos/raw.png', new ImageCropRect(50, 20, 200, 200))
        ->handle(resolve(ThumbnailService::class));

    $user->refresh();
    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($user->profile_photo_path));
    expect($image->getImageWidth())->toBe(200)
        ->and($image->getImageHeight())->toBe(200);
    $image->clear();
});

it('keeps animation in the normalized image and its variants', function (): void {
    Storage::disk('public')->put('profile-photos/raw.gif', makeAnimatedTestImage(3, 400, 400));
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/raw.gif']);

    new NormalizeUserAvatar($user, 'profile-photos/raw.gif')->handle(resolve(ThumbnailService::class));

    $user->refresh();
    $normalized = new Imagick;
    $normalized->readImageBlob((string) Storage::disk('public')->get($user->profile_photo_path));
    expect(mb_strtolower($normalized->getImageFormat()))->toBe('webp')
        ->and($normalized->getNumberImages())->toBe(3);
    $normalized->clear();

    $variant = new Imagick;
    $variant->readImageBlob((string) Storage::disk('public')->get($user->profile_photo_variants[128]));
    expect($variant->getNumberImages())->toBe(3)
        ->and($variant->getImageWidth())->toBe(128);
    $variant->clear();
});

it('bails when a newer upload superseded the source path', function (): void {
    Storage::disk('public')->put('profile-photos/stale.png', makeNormalizeTestImage(256, 256));
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/current.png']);

    new NormalizeUserAvatar($user, 'profile-photos/stale.png')->handle(resolve(ThumbnailService::class));

    expect($user->refresh()->profile_photo_path)->toBe('profile-photos/current.png');
    Storage::disk('public')->assertExists('profile-photos/stale.png');
});

it('bails when the source file is missing', function (): void {
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/missing.png']);

    new NormalizeUserAvatar($user, 'profile-photos/missing.png')->handle(resolve(ThumbnailService::class));

    expect($user->refresh())
        ->profile_photo_path->toBe('profile-photos/missing.png')
        ->profile_photo_variants->toBeNull();
});

it('leaves the raw upload serving when the blob cannot be decoded', function (): void {
    Storage::disk('public')->put('profile-photos/raw.png', 'not-an-image');
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/raw.png']);

    new NormalizeUserAvatar($user, 'profile-photos/raw.png')->handle(resolve(ThumbnailService::class));

    expect($user->refresh())
        ->profile_photo_path->toBe('profile-photos/raw.png')
        ->profile_photo_variants->toBeNull();
    Storage::disk('public')->assertExists('profile-photos/raw.png');
});
