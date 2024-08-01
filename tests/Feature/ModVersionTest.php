<?php

use App\Models\ModVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('includes only published mod versions', function () {
    $publishedMod = ModVersion::factory()->create([
        'published_at' => Carbon::now()->subDay(),
    ]);
    $unpublishedMod = ModVersion::factory()->create([
        'published_at' => Carbon::now()->addDay(),
    ]);
    $noPublishedDateMod = ModVersion::factory()->create([
        'published_at' => null,
    ]);

    $all = ModVersion::withoutGlobalScopes()->get();
    expect($all)->toHaveCount(3);

    $mods = ModVersion::all();

    expect($mods)->toHaveCount(1)
        ->and($mods->contains($publishedMod))->toBeTrue()
        ->and($mods->contains($unpublishedMod))->toBeFalse()
        ->and($mods->contains($noPublishedDateMod))->toBeFalse();
});

it('handles null published_at as not published', function () {
    $modWithNoPublishDate = ModVersion::factory()->create([
        'published_at' => null,
    ]);

    $mods = ModVersion::all();

    expect($mods->contains($modWithNoPublishDate))->toBeFalse();
});
