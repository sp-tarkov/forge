<?php

declare(strict_types=1);

use App\Jobs\NormalizeUserAvatar;
use App\Models\User;
use App\Support\DataTransferObjects\ImageCropRect;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake([NormalizeUserAvatar::class]);
});

it('stores an uploaded profile photo and queues avatar normalization', function (): void {
    $user = User::factory()->create(['profile_photo_path' => null]);

    $user->updateProfilePhoto(UploadedFile::fake()->image('avatar.png', 512, 512));

    expect($user->profile_photo_path)->toStartWith('profile-photos/');
    Storage::disk('public')->assertExists($user->profile_photo_path);
    Queue::assertPushed(fn (NormalizeUserAvatar $job): bool => $job->user->is($user)
        && $job->sourcePath === $user->profile_photo_path
        && ! $job->cropRect instanceof ImageCropRect);
});

it('passes the crop rect to the avatar normalization job', function (): void {
    $user = User::factory()->create(['profile_photo_path' => null]);

    $user->updateProfilePhoto(UploadedFile::fake()->image('avatar.png', 512, 512), new ImageCropRect(10, 20, 300, 300));

    Queue::assertPushed(fn (NormalizeUserAvatar $job): bool => $job->cropRect?->x === 10
        && $job->cropRect->y === 20
        && $job->cropRect->width === 300
        && $job->cropRect->height === 300);
});

it('deletes the previous photo and its variants when replaced', function (): void {
    Storage::disk('public')->put('profile-photos/old.png', 'old-photo');
    Storage::disk('public')->put('profile-photos/old_128w.webp', 'old-variant');
    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/old.png',
        'profile_photo_variants' => [128 => 'profile-photos/old_128w.webp'],
    ]);

    $user->updateProfilePhoto(UploadedFile::fake()->image('avatar.png', 512, 512));

    Storage::disk('public')->assertMissing('profile-photos/old.png');
    Storage::disk('public')->assertMissing('profile-photos/old_128w.webp');
    expect($user->profile_photo_variants)->toBeNull();
});

it('deletes the photo and its variants on delete', function (): void {
    Storage::disk('public')->put('profile-photos/avatar.png', 'photo');
    Storage::disk('public')->put('profile-photos/avatar_128w.webp', 'variant');
    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/avatar.png',
        'profile_photo_variants' => [128 => 'profile-photos/avatar_128w.webp'],
    ]);

    $user->deleteProfilePhoto();

    Storage::disk('public')->assertMissing('profile-photos/avatar.png');
    Storage::disk('public')->assertMissing('profile-photos/avatar_128w.webp');
    expect($user->refresh())
        ->profile_photo_path->toBeNull()
        ->profile_photo_variants->toBeNull();
});

it('prefers the largest variant for the profile photo url', function (): void {
    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/avatar.png',
        'profile_photo_variants' => [
            128 => 'profile-photos/avatar_128w.webp',
            256 => 'profile-photos/avatar_256w.webp',
        ],
    ]);

    expect($user->profile_photo_url)->toBe(Storage::disk('public')->url('profile-photos/avatar_256w.webp'));
});

it('falls back to the original photo url when no variants exist', function (): void {
    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/avatar.png',
        'profile_photo_variants' => null,
    ]);

    expect($user->profile_photo_url)->toBe(Storage::disk('public')->url('profile-photos/avatar.png'));
});

it('builds a srcset from the profile photo variants', function (): void {
    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/avatar.png',
        'profile_photo_variants' => [
            128 => 'profile-photos/avatar_128w.webp',
            256 => 'profile-photos/avatar_256w.webp',
        ],
    ]);

    expect($user->profile_photo_srcset)->toBe(sprintf(
        '%s 128w, %s 256w',
        Storage::disk('public')->url('profile-photos/avatar_128w.webp'),
        Storage::disk('public')->url('profile-photos/avatar_256w.webp'),
    ));
});

it('returns an empty srcset when no variants exist', function (): void {
    $user = User::factory()->create(['profile_photo_variants' => null]);

    expect($user->profile_photo_srcset)->toBe('');
});
