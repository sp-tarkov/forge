<?php

declare(strict_types=1);

use App\Enums\UserImageType;
use App\Jobs\GenerateThumbnailVariants;
use App\Jobs\GenerateUserImageVariants;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

it('dispatches jobs for mods, addons, and lists with thumbnails but no variants', function (): void {
    $mod = Mod::factory()->create(['thumbnail' => 'mods/one.png']);
    $addon = Addon::factory()->create(['thumbnail' => 'addons/one.png']);
    $list = ModList::factory()->create(['thumbnail' => 'mod-lists/one.png']);
    Mod::factory()->create(['thumbnail' => '']);

    $this->artisan('thumbnails:backfill-variants')
        ->expectsOutputToContain('Dispatched 3 image variant generation jobs')
        ->assertSuccessful();

    Queue::assertPushed(GenerateThumbnailVariants::class, 3);
    Queue::assertPushed(fn (GenerateThumbnailVariants $job): bool => $job->model->is($mod));
    Queue::assertPushed(fn (GenerateThumbnailVariants $job): bool => $job->model->is($addon));
    Queue::assertPushed(fn (GenerateThumbnailVariants $job): bool => $job->model->is($list));
});

it('dispatches jobs for user photos and covers missing variants', function (): void {
    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/one.png',
        'cover_photo_path' => 'cover-photos/one.png',
    ]);
    User::factory()->create(['profile_photo_path' => null, 'cover_photo_path' => null]);

    $this->artisan('thumbnails:backfill-variants')->assertSuccessful();

    Queue::assertPushed(GenerateUserImageVariants::class, 2);
    Queue::assertPushed(fn (GenerateUserImageVariants $job): bool => $job->user->is($user)
        && $job->type === UserImageType::ProfilePhoto);
    Queue::assertPushed(fn (GenerateUserImageVariants $job): bool => $job->user->is($user)
        && $job->type === UserImageType::CoverPhoto);
});

it('skips user images that already have variants', function (): void {
    User::factory()->create([
        'profile_photo_path' => 'profile-photos/one.png',
        'profile_photo_variants' => [128 => 'profile-photos/one_128w.webp'],
    ]);

    $this->artisan('thumbnails:backfill-variants')->assertSuccessful();

    Queue::assertNotPushed(GenerateUserImageVariants::class);
});

it('regenerates existing user image variants when forced', function (): void {
    User::factory()->create([
        'profile_photo_path' => 'profile-photos/one.png',
        'profile_photo_variants' => [128 => 'profile-photos/one_128w.webp'],
    ]);

    $this->artisan('thumbnails:backfill-variants', ['--force' => true])->assertSuccessful();

    Queue::assertPushed(GenerateUserImageVariants::class, 1);
});

it('skips models that already have variants', function (): void {
    Mod::factory()->create([
        'thumbnail' => 'mods/one.png',
        'thumbnail_variants' => [192 => 'mods/one_192w.webp'],
    ]);

    $this->artisan('thumbnails:backfill-variants')->assertSuccessful();

    Queue::assertNotPushed(GenerateThumbnailVariants::class);
});

it('regenerates existing variants when forced', function (): void {
    Mod::factory()->create([
        'thumbnail' => 'mods/one.png',
        'thumbnail_variants' => [192 => 'mods/one_192w.webp'],
    ]);

    $this->artisan('thumbnails:backfill-variants', ['--force' => true])->assertSuccessful();

    Queue::assertPushed(GenerateThumbnailVariants::class, 1);
});

it('includes unpublished and disabled mods', function (): void {
    Mod::factory()->create(['thumbnail' => 'mods/one.png', 'published_at' => null]);
    Mod::factory()->disabled()->create(['thumbnail' => 'mods/two.png']);

    $this->artisan('thumbnails:backfill-variants')->assertSuccessful();

    Queue::assertPushed(GenerateThumbnailVariants::class, 2);
});
