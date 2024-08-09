<?php

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the latest version on the mod detail page', function () {
    // Create a mod instance
    $mod = Mod::factory()->create();

    // Create 5 mod versions with specified versions
    $versions = [
        '1.0.0',
        '1.1.0',
        '1.2.0',
        '2.0.0',
        '2.1.0',
    ];

    // get the highest version in the array
    $latestVersion = max($versions);

    foreach ($versions as $version) {
        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'version' => $version,
        ]);
    }

    // Make a request to the mod's detail URL
    $response = $this->get($mod->detailUrl());

    $this->assertEquals('2.1.0', $latestVersion);

    // Assert the latest version is next to the mod's name
    $response->assertSeeInOrder(explode(' ', "$mod->name $latestVersion"));

    // Assert the latest version is in the latest download button
    $response->assertSeeText(__('Download Latest Version')." ($latestVersion)");
});
