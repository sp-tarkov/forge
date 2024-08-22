<?php

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can retrieve all unresolved versions', function () {
    // Create a mod instance
    $mod = Mod::factory()->create();
    ModVersion::factory(5)->recycle($mod)->create();

    ModVersion::all()->each(function (ModVersion $modVersion) {
        $modVersion->resolved_spt_version_id = null;
        $modVersion->saveQuietly();
    });

    $unresolvedMix = $mod->versions(resolvedOnly: false);

    $unresolvedMix->each(function (ModVersion $modVersion) {
        expect($modVersion)->toBeInstanceOf(ModVersion::class)
            ->and($modVersion->resolved_spt_version_id)->toBeNull();
    });

    expect($unresolvedMix->count())->toBe(5)
        ->and($mod->versions->count())->toBe(0);
});

it('shows the latest version on the mod detail page', function () {
    $versions = [
        '1.0.0',
        '1.1.0',
        '1.2.0',
        '2.0.0',
        '2.1.0',
    ];
    $latestVersion = max($versions);

    $mod = Mod::factory()->create();
    foreach ($versions as $version) {
        ModVersion::factory()->sptVersionResolved()->recycle($mod)->create(['version' => $version]);
    }

    $response = $this->get($mod->detailUrl());

    expect($latestVersion)->toBe('2.1.0');

    // Assert the latest version is next to the mod's name
    $response->assertSeeInOrder(explode(' ', "$mod->name $latestVersion"));

    // Assert the latest version is in the latest download button
    $response->assertSeeText(__('Download Latest Version')." ($latestVersion)");
});
