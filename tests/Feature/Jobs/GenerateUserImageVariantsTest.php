<?php

declare(strict_types=1);

use App\Enums\UserImageType;
use App\Jobs\GenerateUserImageVariants;
use App\Models\User;
use App\Services\ThumbnailService;
use Illuminate\Support\Facades\Storage;

function makeUserImageTestImage(int $width, int $height): string
{
    $image = new Imagick;
    $image->newImage($width, $height, new ImagickPixel('green'));
    $image->setImageFormat('png');

    $blob = $image->getImageBlob();
    $image->clear();

    return $blob;
}

beforeEach(function (): void {
    Storage::fake('public');
});

it('generates square avatar variants from a non-square source', function (): void {
    Storage::disk('public')->put('profile-photos/avatar.png', makeUserImageTestImage(800, 400));
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/avatar.png']);

    new GenerateUserImageVariants($user, UserImageType::ProfilePhoto)->handle(resolve(ThumbnailService::class));

    $user->refresh();
    expect($user->profile_photo_variants)->toBe([
        128 => 'profile-photos/avatar_128w.webp',
        256 => 'profile-photos/avatar_256w.webp',
    ]);

    foreach ($user->profile_photo_variants as $width => $path) {
        $image = new Imagick;
        $image->readImageBlob((string) Storage::disk('public')->get($path));
        expect($image->getImageWidth())->toBe($width)
            ->and($image->getImageHeight())->toBe($width);
        $image->clear();
    }
});

it('generates aspect-preserving cover variants', function (): void {
    Storage::disk('public')->put('cover-photos/banner.png', makeUserImageTestImage(3000, 1500));
    $user = User::factory()->create(['cover_photo_path' => 'cover-photos/banner.png']);

    new GenerateUserImageVariants($user, UserImageType::CoverPhoto)->handle(resolve(ThumbnailService::class));

    $user->refresh();
    expect($user->cover_photo_variants)->toBe([
        1280 => 'cover-photos/banner_1280w.webp',
        2560 => 'cover-photos/banner_2560w.webp',
    ]);

    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($user->cover_photo_variants[1280]));
    expect($image->getImageWidth())->toBe(1280)
        ->and($image->getImageHeight())->toBe(640);
    $image->clear();
});

it('skips avatar widths that would upscale the source', function (): void {
    Storage::disk('public')->put('profile-photos/small.png', makeUserImageTestImage(200, 200));
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/small.png']);

    new GenerateUserImageVariants($user, UserImageType::ProfilePhoto)->handle(resolve(ThumbnailService::class));

    expect($user->refresh()->profile_photo_variants)->toBe([128 => 'profile-photos/small_128w.webp']);
    Storage::disk('public')->assertMissing('profile-photos/small_256w.webp');
});

it('deletes stale variant files before regenerating', function (): void {
    Storage::disk('public')->put('profile-photos/old_128w.webp', 'stale-variant');
    Storage::disk('public')->put('profile-photos/new.png', makeUserImageTestImage(512, 512));
    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/new.png',
        'profile_photo_variants' => [128 => 'profile-photos/old_128w.webp'],
    ]);

    new GenerateUserImageVariants($user, UserImageType::ProfilePhoto)->handle(resolve(ThumbnailService::class));

    Storage::disk('public')->assertMissing('profile-photos/old_128w.webp');
    expect($user->refresh()->profile_photo_variants)->toBe([
        128 => 'profile-photos/new_128w.webp',
        256 => 'profile-photos/new_256w.webp',
    ]);
});

it('clears variants when the user has no image path', function (): void {
    Storage::disk('public')->put('profile-photos/orphan_128w.webp', 'stale-variant');
    $user = User::factory()->create([
        'profile_photo_path' => null,
        'profile_photo_variants' => [128 => 'profile-photos/orphan_128w.webp'],
    ]);

    new GenerateUserImageVariants($user, UserImageType::ProfilePhoto)->handle(resolve(ThumbnailService::class));

    Storage::disk('public')->assertMissing('profile-photos/orphan_128w.webp');
    expect($user->refresh()->profile_photo_variants)->toBeNull();
});

it('keeps animation in profile photo variants', function (): void {
    Storage::disk('public')->put('profile-photos/animated.gif', makeAnimatedTestImage(3, 512, 512));
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/animated.gif']);

    new GenerateUserImageVariants($user, UserImageType::ProfilePhoto)->handle(resolve(ThumbnailService::class));

    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($user->refresh()->profile_photo_variants[128]));

    expect($image->getNumberImages())->toBe(3);
    $image->clear();
});

it('flattens animation in cover photo variants', function (): void {
    Storage::disk('public')->put('cover-photos/animated.gif', makeAnimatedTestImage(3, 1500, 750));
    $user = User::factory()->create(['cover_photo_path' => 'cover-photos/animated.gif']);

    new GenerateUserImageVariants($user, UserImageType::CoverPhoto)->handle(resolve(ThumbnailService::class));

    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($user->refresh()->cover_photo_variants[1280]));

    expect($image->getNumberImages())->toBe(1);
    $image->clear();
});

it('leaves the profile photo columns untouched when regenerating cover variants', function (): void {
    Storage::disk('public')->put('cover-photos/banner.png', makeUserImageTestImage(1500, 750));
    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/avatar.png',
        'profile_photo_variants' => [128 => 'profile-photos/avatar_128w.webp'],
        'cover_photo_path' => 'cover-photos/banner.png',
    ]);

    new GenerateUserImageVariants($user, UserImageType::CoverPhoto)->handle(resolve(ThumbnailService::class));

    $user->refresh();
    expect($user->profile_photo_path)->toBe('profile-photos/avatar.png')
        ->and($user->profile_photo_variants)->toBe([128 => 'profile-photos/avatar_128w.webp'])
        ->and($user->cover_photo_variants)->toBe([1280 => 'cover-photos/banner_1280w.webp']);
});
