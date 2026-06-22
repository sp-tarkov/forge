<?php

declare(strict_types=1);

use App\Models\User;
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
