<?php

declare(strict_types=1);

use App\Http\Filters\ModFilter;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters mods by a single SPT version', function (): void {
    $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
    $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

    $mod1 = Mod::factory()->create();
    $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
        'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
    ]);

    $mod2 = Mod::factory()->create();
    $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
        'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
    ]);

    // Confirm associations created by observers and services
    expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
        ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

    // Apply the filter
    $filters = ['sptVersions' => [$sptVersion1->version]];
    $filteredMods = (new ModFilter($filters))->apply()->get();

    // Assert that only the correct mod is returned
    expect($filteredMods)->toHaveCount(1)
        ->and($filteredMods->first()->id)->toBe($mod1->id);
});

it('filters mods by multiple SPT versions', function (): void {
    // Create the SPT versions
    $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
    $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);
    $sptVersion3 = SptVersion::factory()->create(['version' => '3.0.0']);

    // Create the mods and their versions with appropriate constraints
    $mod1 = Mod::factory()->create();
    $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
        'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
    ]);

    $mod2 = Mod::factory()->create();
    $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
        'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
    ]);

    $mod3 = Mod::factory()->create();
    $modVersion3 = ModVersion::factory()->recycle($mod3)->create([
        'spt_version_constraint' => '3.0.0', // Constraint matching sptVersion3
    ]);

    // Confirm associations created by observers and services
    expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
        ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version)
        ->and($modVersion3->sptVersions->pluck('version')->toArray())->toContain($sptVersion3->version);

    // Apply the filter with multiple SPT versions
    $filters = ['sptVersions' => [$sptVersion1->version, $sptVersion3->version]];
    $filteredMods = (new ModFilter($filters))->apply()->get();

    // Assert that the correct mods are returned
    expect($filteredMods)->toHaveCount(2)
        ->and($filteredMods->pluck('id')->toArray())->toContain($mod1->id, $mod3->id);
});

it('returns no mods when no SPT versions match', function (): void {
    // Create the SPT versions
    $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
    $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

    // Create the mods and their versions with appropriate constraints
    $mod1 = Mod::factory()->create();
    $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
        'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
    ]);

    $mod2 = Mod::factory()->create();
    $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
        'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
    ]);

    // Confirm associations created by observers and services
    expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
        ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

    // Apply the filter with a non-matching SPT version
    $filters = ['sptVersions' => ['3.0.0']]; // Version '3.0.0' does not exist in associations
    $filteredMods = (new ModFilter($filters))->apply()->get();

    // Assert that no mods are returned
    expect($filteredMods)->toBeEmpty();
});

it('filters mods based on a exact search term', function (): void {
    SptVersion::factory()->create(['version' => '1.0.0']);

    $mod = Mod::factory()->create(['name' => 'BigBrain']);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

    Mod::factory()->create(['name' => 'SmallFeet']);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

    $filters = ['query' => 'BigBrain'];
    $filteredMods = (new ModFilter($filters))->apply()->get();

    expect($filteredMods)->toHaveCount(1)->and($filteredMods->first()->id)->toBe($mod->id);
});

it('filters mods based featured status', function (): void {
    SptVersion::factory()->create(['version' => '1.0.0']);

    $mod = Mod::factory()->create(['name' => 'BigBrain', 'featured' => true]);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

    Mod::factory()->create(['name' => 'SmallFeet']);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '^1.0.0']);

    $filters = ['featured' => 'only'];
    $filteredMods = (new ModFilter($filters))->apply()->get();

    expect($filteredMods)->toHaveCount(1)->and($filteredMods->first()->id)->toBe($mod->id);
});

it('filters mods correctly with combined filters', function (): void {
    // Create the SPT versions
    $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
    $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

    // Create the mods and their versions with appropriate names and featured status
    $mod1 = Mod::factory()->create(['name' => 'Awesome Mod', 'featured' => true]);
    $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
        'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
    ]);

    $mod2 = Mod::factory()->create(['name' => 'Cool Mod', 'featured' => false]);
    $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
        'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
    ]);

    // Confirm associations created by observers and services
    expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
        ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

    // Apply combined filters
    $filters = [
        'query' => 'Awesome',
        'featured' => 'only',
        'sptVersions' => [$sptVersion1->version],
    ];
    $filteredMods = (new ModFilter($filters))->apply()->get();

    // Assert that only the correct mod is returned
    expect($filteredMods)->toHaveCount(1)->and($filteredMods->first()->id)->toBe($mod1->id);
});

it('handles an empty SPT versions array correctly', function (): void {
    // Create the SPT versions
    $sptVersion1 = SptVersion::factory()->create(['version' => '1.0.0']);
    $sptVersion2 = SptVersion::factory()->create(['version' => '2.0.0']);

    // Create the mods and their versions with appropriate constraints
    $mod1 = Mod::factory()->create();
    $modVersion1 = ModVersion::factory()->recycle($mod1)->create([
        'spt_version_constraint' => '1.0.0', // Constraint matching sptVersion1
    ]);

    $mod2 = Mod::factory()->create();
    $modVersion2 = ModVersion::factory()->recycle($mod2)->create([
        'spt_version_constraint' => '2.0.0', // Constraint matching sptVersion2
    ]);

    // Confirm associations created by observers and services
    expect($modVersion1->sptVersions->pluck('version')->toArray())->toContain($sptVersion1->version)
        ->and($modVersion2->sptVersions->pluck('version')->toArray())->toContain($sptVersion2->version);

    // Apply the filter with an empty SPT versions array
    $filters = ['sptVersions' => []];
    $filteredMods = (new ModFilter($filters))->apply()->get();

    // Assert that the behavior is as expected (return all mods, or none, depending on intended behavior)
    expect($filteredMods)->toHaveCount(2); // Modify this assertion to reflect your desired behavior
});
