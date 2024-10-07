<?php

use App\Models\Mod;
use App\Models\ModVersion;

it('displays the latest version on the mod detail page', function () {
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
        ModVersion::factory()->recycle($mod)->create(['version' => $version]);
    }

    $response = $this->get($mod->detailUrl());

    expect($latestVersion)->toBe('2.1.0');

    // Assert the latest version is next to the mod's name
    $response->assertSeeInOrder(explode(' ', "$mod->name $latestVersion"));

    // Assert the latest version is in the latest download button
    $response->assertSeeText(__('Download Latest Version')." ($latestVersion)");
});

it('builds download links using the latest mod version', function () {
    $mod = Mod::factory()->create(['id' => 1, 'slug' => 'test-mod']);
    ModVersion::factory()->recycle($mod)->create(['version' => '1.2.3']);
    ModVersion::factory()->recycle($mod)->create(['version' => '1.3.0']);
    $modVersion = ModVersion::factory()->recycle($mod)->create(['version' => '1.3.4']);

    expect($mod->downloadUrl())->toEqual(route('mod.version.download', [
        'mod' => $mod->id,
        'slug' => $mod->slug,
        'version' => $modVersion->version,
    ], absolute: false));
});
