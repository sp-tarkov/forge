<?php

declare(strict_types=1);

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Jobs\NormalizeUserAvatar;
use App\Models\User;
use App\Support\DataTransferObjects\ImageCropRect;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function makeProfileUpdateInput(User $user, array $overrides = []): array
{
    return array_merge([
        'name' => $user->name,
        'email' => $user->email,
        'timezone' => 'America/New_York',
        'about' => 'Short about text.',
        'photo' => UploadedFile::fake()->image('avatar.png', 512, 512),
    ], $overrides);
}

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake([NormalizeUserAvatar::class]);
});

it('passes a valid crop rect to the avatar normalization job as a DTO', function (): void {
    $user = User::factory()->create();

    resolve(UpdateUserProfileInformation::class)->update($user, makeProfileUpdateInput($user, [
        'photoCropRect' => ['x' => 10, 'y' => 20, 'width' => 300, 'height' => 300],
    ]));

    Queue::assertPushed(fn (NormalizeUserAvatar $job): bool => $job->user->is($user)
        && $job->cropRect?->x === 10
        && $job->cropRect->y === 20
        && $job->cropRect->width === 300
        && $job->cropRect->height === 300);
});

it('passes a null crop rect when none is provided', function (): void {
    $user = User::factory()->create();

    resolve(UpdateUserProfileInformation::class)->update($user, makeProfileUpdateInput($user));

    Queue::assertPushed(fn (NormalizeUserAvatar $job): bool => ! $job->cropRect instanceof ImageCropRect);
});

it('rejects a crop rect with a missing key', function (): void {
    $user = User::factory()->create();

    resolve(UpdateUserProfileInformation::class)->update($user, makeProfileUpdateInput($user, [
        'photoCropRect' => ['x' => 0, 'y' => 0, 'width' => 300],
    ]));
})->throws(ValidationException::class);

it('rejects a crop rect with a negative origin', function (): void {
    $user = User::factory()->create();

    resolve(UpdateUserProfileInformation::class)->update($user, makeProfileUpdateInput($user, [
        'photoCropRect' => ['x' => -5, 'y' => 0, 'width' => 300, 'height' => 300],
    ]));
})->throws(ValidationException::class);

it('rejects a crop rect below the minimum dimensions', function (): void {
    $user = User::factory()->create();

    resolve(UpdateUserProfileInformation::class)->update($user, makeProfileUpdateInput($user, [
        'photoCropRect' => ['x' => 0, 'y' => 0, 'width' => 64, 'height' => 64],
    ]));
})->throws(ValidationException::class);
