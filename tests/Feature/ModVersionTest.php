<?php

use App\Models\ModVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

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

    $mods = ModVersion::all();

    expect($mods)->toHaveCount(1);
    expect($mods->contains($publishedMod))->toBeTrue();
    expect($mods->contains($unpublishedMod))->toBeFalse();
    expect($mods->contains($noPublishedDateMod))->toBeFalse();
});

it('handles null published_at as not published', function () {
    $modWithNoPublishDate = ModVersion::factory()->create([
        'published_at' => null,
    ]);

    $mods = ModVersion::all();

    expect($mods->contains($modWithNoPublishDate))->toBeFalse();
});
