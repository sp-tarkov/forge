<?php

declare(strict_types=1);

use App\Jobs\GenerateThumbnailVariants;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Services\ThumbnailService;
use Illuminate\Support\Facades\Storage;

function makeVariantJobTestImage(int $width, int $height): string
{
    $image = new Imagick;
    $image->newImage($width, $height, new ImagickPixel('blue'));
    $image->setImageFormat('png');

    $blob = $image->getImageBlob();
    $image->clear();

    return $blob;
}

beforeEach(function (): void {
    Storage::fake('public');
});

it('generates and stores thumbnail variants for a mod', function (): void {
    Storage::disk('public')->put('mods/thumb.png', makeVariantJobTestImage(512, 512));
    $mod = Mod::factory()->create(['thumbnail' => 'mods/thumb.png']);

    new GenerateThumbnailVariants($mod)->handle(resolve(ThumbnailService::class));

    $mod->refresh();
    expect($mod->thumbnail_variants)->toBe([
        192 => 'mods/thumb_192w.webp',
        384 => 'mods/thumb_384w.webp',
    ]);
    Storage::disk('public')->assertExists('mods/thumb_192w.webp');
    Storage::disk('public')->assertExists('mods/thumb_384w.webp');
});

it('generates and stores thumbnail variants for an addon', function (): void {
    Storage::disk('public')->put('addons/thumb.png', makeVariantJobTestImage(512, 512));
    $addon = Addon::factory()->create(['thumbnail' => 'addons/thumb.png']);

    new GenerateThumbnailVariants($addon)->handle(resolve(ThumbnailService::class));

    $addon->refresh();
    expect($addon->thumbnail_variants)->toBe([
        192 => 'addons/thumb_192w.webp',
        384 => 'addons/thumb_384w.webp',
    ]);
    Storage::disk('public')->assertExists('addons/thumb_192w.webp');
    Storage::disk('public')->assertExists('addons/thumb_384w.webp');
});

it('generates and stores thumbnail variants for a mod list', function (): void {
    Storage::disk('public')->put('mod-lists/thumb.png', makeVariantJobTestImage(512, 512));
    $list = ModList::factory()->create(['thumbnail' => 'mod-lists/thumb.png']);

    new GenerateThumbnailVariants($list)->handle(resolve(ThumbnailService::class));

    $list->refresh();
    expect($list->thumbnail_variants)->toBe([
        192 => 'mod-lists/thumb_192w.webp',
        384 => 'mod-lists/thumb_384w.webp',
    ]);
    Storage::disk('public')->assertExists('mod-lists/thumb_192w.webp');
    Storage::disk('public')->assertExists('mod-lists/thumb_384w.webp');
});

it('deletes stale variant files before regenerating', function (): void {
    Storage::disk('public')->put('mods/old_192w.webp', 'stale-variant');
    Storage::disk('public')->put('mods/new.png', makeVariantJobTestImage(512, 512));
    $mod = Mod::factory()->create([
        'thumbnail' => 'mods/new.png',
        'thumbnail_variants' => [192 => 'mods/old_192w.webp'],
    ]);

    new GenerateThumbnailVariants($mod)->handle(resolve(ThumbnailService::class));

    Storage::disk('public')->assertMissing('mods/old_192w.webp');
    expect($mod->refresh()->thumbnail_variants)->toBe([
        192 => 'mods/new_192w.webp',
        384 => 'mods/new_384w.webp',
    ]);
});

it('clears variants when the model has no thumbnail', function (): void {
    Storage::disk('public')->put('mods/orphan_192w.webp', 'stale-variant');
    $mod = Mod::factory()->create([
        'thumbnail' => '',
        'thumbnail_variants' => [192 => 'mods/orphan_192w.webp'],
    ]);

    new GenerateThumbnailVariants($mod)->handle(resolve(ThumbnailService::class));

    Storage::disk('public')->assertMissing('mods/orphan_192w.webp');
    expect($mod->refresh()->thumbnail_variants)->toBeNull();
});

it('stores an empty variant list when the source file is missing', function (): void {
    $mod = Mod::factory()->create(['thumbnail' => 'mods/missing.png']);

    new GenerateThumbnailVariants($mod)->handle(resolve(ThumbnailService::class));

    expect($mod->refresh()->thumbnail_variants)->toBe([]);
});
