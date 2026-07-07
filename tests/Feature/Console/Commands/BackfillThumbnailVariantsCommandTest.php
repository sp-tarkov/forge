<?php

declare(strict_types=1);

use App\Jobs\GenerateThumbnailVariants;
use App\Models\Addon;
use App\Models\Mod;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

it('dispatches jobs for mods and addons with thumbnails but no variants', function (): void {
    $mod = Mod::factory()->create(['thumbnail' => 'mods/one.png']);
    $addon = Addon::factory()->create(['thumbnail' => 'addons/one.png']);
    Mod::factory()->create(['thumbnail' => '']);

    $this->artisan('thumbnails:backfill-variants')
        ->expectsOutputToContain('Dispatched 2 thumbnail variant generation jobs')
        ->assertSuccessful();

    Queue::assertPushed(GenerateThumbnailVariants::class, 2);
    Queue::assertPushed(fn (GenerateThumbnailVariants $job): bool => $job->model->is($mod));
    Queue::assertPushed(fn (GenerateThumbnailVariants $job): bool => $job->model->is($addon));
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
