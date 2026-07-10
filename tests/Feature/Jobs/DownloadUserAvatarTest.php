<?php

declare(strict_types=1);

use App\Enums\UserImageType;
use App\Jobs\DownloadUserAvatar;
use App\Jobs\GenerateUserImageVariants;
use App\Models\User;
use App\Services\ThumbnailService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function makeAvatarDownloadTestImage(int $width, int $height): string
{
    $image = new Imagick;
    $image->newImage($width, $height, new ImagickPixel('purple'));
    $image->setImageFormat('png');

    $blob = $image->getImageBlob();
    $image->clear();

    return $blob;
}

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake([GenerateUserImageVariants::class]);
});

it('stores the downloaded avatar as a square webp and queues variant generation', function (): void {
    Http::fake(['example.com/*' => Http::response(makeAvatarDownloadTestImage(400, 200))]);
    $user = User::factory()->create(['profile_photo_path' => null]);

    new DownloadUserAvatar($user, 'https://example.com/avatar.png')->handle(resolve(ThumbnailService::class));

    $user->refresh();
    expect($user->profile_photo_path)->toStartWith('profile-photos/')
        ->and($user->profile_photo_path)->toEndWith('.webp')
        ->and($user->profile_photo_variants)->toBeNull();

    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($user->profile_photo_path));
    expect(mb_strtolower($image->getImageFormat()))->toBe('webp')
        ->and($image->getImageWidth())->toBe(200)
        ->and($image->getImageHeight())->toBe(200);
    $image->clear();

    Queue::assertPushed(fn (GenerateUserImageVariants $job): bool => $job->user->is($user)
        && $job->type === UserImageType::ProfilePhoto);
});

it('replaces the previous photo and variant files', function (): void {
    Http::fake(['example.com/*' => Http::response(makeAvatarDownloadTestImage(300, 300))]);
    Storage::disk('public')->put('profile-photos/old.png', 'old-photo');
    Storage::disk('public')->put('profile-photos/old_128w.webp', 'old-variant');
    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/old.png',
        'profile_photo_variants' => [128 => 'profile-photos/old_128w.webp'],
    ]);

    new DownloadUserAvatar($user, 'https://example.com/avatar.png')->handle(resolve(ThumbnailService::class));

    Storage::disk('public')->assertMissing('profile-photos/old.png');
    Storage::disk('public')->assertMissing('profile-photos/old_128w.webp');
    expect($user->refresh()->profile_photo_path)->not->toBe('profile-photos/old.png');
});

it('keeps animation when the downloaded avatar is animated', function (): void {
    Http::fake(['example.com/*' => Http::response(makeAnimatedTestImage(3, 300, 200))]);
    $user = User::factory()->create(['profile_photo_path' => null]);

    new DownloadUserAvatar($user, 'https://example.com/avatar.gif')->handle(resolve(ThumbnailService::class));

    $user->refresh();
    $image = new Imagick;
    $image->readImageBlob((string) Storage::disk('public')->get($user->profile_photo_path));
    expect(mb_strtolower($image->getImageFormat()))->toBe('webp')
        ->and($image->getNumberImages())->toBe(3)
        ->and($image->getImageWidth())->toBe(200)
        ->and($image->getImageHeight())->toBe(200);
    $image->clear();

    Queue::assertPushed(GenerateUserImageVariants::class);
});

it('leaves the user untouched when the download fails', function (): void {
    Http::fake(['example.com/*' => Http::response('', 404)]);
    $user = User::factory()->create(['profile_photo_path' => null]);

    new DownloadUserAvatar($user, 'https://example.com/avatar.png')->handle(resolve(ThumbnailService::class));

    expect($user->refresh()->profile_photo_path)->toBeNull();
    Queue::assertNotPushed(GenerateUserImageVariants::class);
});

it('leaves the user untouched when the response is not a valid image', function (): void {
    Http::fake(['example.com/*' => Http::response('not-an-image')]);
    $user = User::factory()->create(['profile_photo_path' => null]);

    new DownloadUserAvatar($user, 'https://example.com/avatar.png')->handle(resolve(ThumbnailService::class));

    expect($user->refresh()->profile_photo_path)->toBeNull();
    Queue::assertNotPushed(GenerateUserImageVariants::class);
});
