<?php

declare(strict_types=1);

use App\Enums\UserImageType;
use App\Jobs\GenerateUserImageVariants;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('returns null for the cover photo url when no cover photo has been uploaded', function (): void {
    $user = User::factory()->create(['cover_photo_path' => null]);

    expect($user->cover_photo_url)->toBeNull();
});

it('returns the storage url for the cover photo when one has been uploaded', function (): void {
    Storage::fake('public');

    $user = User::factory()->create(['cover_photo_path' => 'cover-photos/banner.png']);

    expect($user->cover_photo_url)->toBe(Storage::disk('public')->url('cover-photos/banner.png'));
});

it('builds a valid css gradient for the cover photo placeholder', function (): void {
    $user = User::factory()->create(['name' => 'Example User']);

    expect($user->cover_photo_gradient)->toMatch(
        '/^linear-gradient\(135deg, hsl\(\d{1,3}, 65%, 55%\) 0%, hsl\(\d{1,3}, 65%, 45%\) 100%\)$/'
    );
});

it('derives the cover photo gradient deterministically from the name', function (): void {
    $user = User::factory()->create(['name' => 'Stable Name']);

    $hue = crc32('Stable Name') % 360;
    $secondHue = ($hue + 50) % 360;
    $expected = sprintf(
        'linear-gradient(135deg, hsl(%d, 65%%, 55%%) 0%%, hsl(%d, 65%%, 45%%) 100%%)',
        $hue,
        $secondHue,
    );

    expect($user->cover_photo_gradient)->toBe($expected);
});

it('produces different cover photo gradients for different names', function (): void {
    $alpha = User::factory()->create(['name' => 'Alpha']);
    $beta = User::factory()->create(['name' => 'Beta']);

    expect($alpha->cover_photo_gradient)->not->toBe($beta->cover_photo_gradient);
});

it('stores an uploaded cover photo and queues variant generation', function (): void {
    Storage::fake('public');
    Queue::fake([GenerateUserImageVariants::class]);
    $user = User::factory()->create(['cover_photo_path' => null]);

    $user->updateCoverPhoto(UploadedFile::fake()->image('banner.png', 1600, 400));

    expect($user->cover_photo_path)->toStartWith('cover-photos/');
    Storage::disk('public')->assertExists($user->cover_photo_path);
    Queue::assertPushed(fn (GenerateUserImageVariants $job): bool => $job->user->is($user)
        && $job->type === UserImageType::CoverPhoto);
});

it('deletes the previous cover photo and its variants when replaced', function (): void {
    Storage::fake('public');
    Queue::fake([GenerateUserImageVariants::class]);
    Storage::disk('public')->put('cover-photos/old.png', 'old-cover');
    Storage::disk('public')->put('cover-photos/old_1280w.webp', 'old-variant');
    $user = User::factory()->create([
        'cover_photo_path' => 'cover-photos/old.png',
        'cover_photo_variants' => [1280 => 'cover-photos/old_1280w.webp'],
    ]);

    $user->updateCoverPhoto(UploadedFile::fake()->image('banner.png', 1600, 400));

    Storage::disk('public')->assertMissing('cover-photos/old.png');
    Storage::disk('public')->assertMissing('cover-photos/old_1280w.webp');
    expect($user->cover_photo_variants)->toBeNull();
});

it('deletes the cover photo and its variants on delete', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('cover-photos/banner.png', 'cover');
    Storage::disk('public')->put('cover-photos/banner_1280w.webp', 'variant');
    $user = User::factory()->create([
        'cover_photo_path' => 'cover-photos/banner.png',
        'cover_photo_variants' => [1280 => 'cover-photos/banner_1280w.webp'],
    ]);

    $user->deleteCoverPhoto();

    Storage::disk('public')->assertMissing('cover-photos/banner.png');
    Storage::disk('public')->assertMissing('cover-photos/banner_1280w.webp');
    expect($user->refresh())
        ->cover_photo_path->toBeNull()
        ->cover_photo_variants->toBeNull();
});

it('prefers the largest variant for the cover photo url', function (): void {
    Storage::fake('public');
    $user = User::factory()->create([
        'cover_photo_path' => 'cover-photos/banner.png',
        'cover_photo_variants' => [
            1280 => 'cover-photos/banner_1280w.webp',
            2560 => 'cover-photos/banner_2560w.webp',
        ],
    ]);

    expect($user->cover_photo_url)->toBe(Storage::disk('public')->url('cover-photos/banner_2560w.webp'));
});

it('builds a srcset from the cover photo variants', function (): void {
    Storage::fake('public');
    $user = User::factory()->create([
        'cover_photo_path' => 'cover-photos/banner.png',
        'cover_photo_variants' => [
            1280 => 'cover-photos/banner_1280w.webp',
            2560 => 'cover-photos/banner_2560w.webp',
        ],
    ]);

    expect($user->cover_photo_srcset)->toBe(sprintf(
        '%s 1280w, %s 2560w',
        Storage::disk('public')->url('cover-photos/banner_1280w.webp'),
        Storage::disk('public')->url('cover-photos/banner_2560w.webp'),
    ));
});
