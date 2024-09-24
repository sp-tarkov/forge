<?php

use App\Models\Mod;
use App\Models\ModDependency;
use App\Models\ModResolvedDependency;
use App\Models\ModVersion;
use App\Services\DependencyVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves mod version dependencies on create', function () {
    $modVersion = ModVersion::factory()->create();

    $dependentMod = Mod::factory()->create();
    $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0']);
    $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0']);

    // Create a dependency
    ModDependency::factory()->recycle([$modVersion, $dependentMod])->create([
        'constraint' => '^1.0', // Should resolve to dependentVersion1
    ]);

    // Check that the resolved dependency has been created
    expect(ModResolvedDependency::where('mod_version_id', $modVersion->id)->first())
        ->not()->toBeNull()
        ->resolved_mod_version_id->toBe($dependentVersion1->id);
});

it('resolves multiple matching versions', function () {
    $modVersion = ModVersion::factory()->create();

    $dependentMod = Mod::factory()->create();
    $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0']);
    $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.1.0']);
    $dependentVersion3 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0']);

    // Create a dependency
    ModDependency::factory()->recycle([$modVersion, $dependentMod])->create([
        'constraint' => '^1.0', // Should resolve to dependentVersion1 and dependentVersion2
    ]);

    $resolvedDependencies = ModResolvedDependency::where('mod_version_id', $modVersion->id)->get();

    expect($resolvedDependencies->count())->toBe(2)
        ->and($resolvedDependencies->pluck('resolved_mod_version_id'))
        ->toContain($dependentVersion1->id)
        ->toContain($dependentVersion2->id);
});

it('does not resolve dependencies when no versions match', function () {
    $modVersion = ModVersion::factory()->create();

    $dependentMod = Mod::factory()->create();
    ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0']);
    ModVersion::factory()->recycle($dependentMod)->create(['version' => '3.0.0']);

    // Create a dependency
    ModDependency::factory()->recycle([$modVersion, $dependentMod])->create([
        'constraint' => '^1.0', // No versions match
    ]);

    // Check that no resolved dependencies were created
    expect(ModResolvedDependency::where('mod_version_id', $modVersion->id)->exists())->toBeFalse();
});

it('updates resolved dependencies when constraint changes', function () {
    $modVersion = ModVersion::factory()->create();

    $dependentMod = Mod::factory()->create();
    $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0']);
    $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0']);

    // Create a dependency with an initial constraint
    $dependency = ModDependency::factory()->recycle([$modVersion, $dependentMod])->create([
        'constraint' => '^1.0', // Should resolve to dependentVersion1
    ]);

    $resolvedDependency = ModResolvedDependency::where('mod_version_id', $modVersion->id)->first();
    expect($resolvedDependency->resolved_mod_version_id)->toBe($dependentVersion1->id);

    // Update the constraint
    $dependency->update(['constraint' => '^2.0']); // Should now resolve to dependentVersion2

    $resolvedDependency = ModResolvedDependency::where('mod_version_id', $modVersion->id)->first();
    expect($resolvedDependency->resolved_mod_version_id)->toBe($dependentVersion2->id);
});

it('removes resolved dependencies when dependency is removed', function () {
    $modVersion = ModVersion::factory()->create();

    $dependentMod = Mod::factory()->create();
    $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0']);

    // Create a dependency
    $dependency = ModDependency::factory()->recycle([$modVersion, $dependentMod])->create([
        'constraint' => '^1.0',
    ]);

    $resolvedDependency = ModResolvedDependency::where('mod_version_id', $modVersion->id)->first();
    expect($resolvedDependency)->not()->toBeNull();

    // Delete the dependency
    $dependency->delete();

    // Check that the resolved dependency is removed
    expect(ModResolvedDependency::where('mod_version_id', $modVersion->id)->exists())->toBeFalse();
});

it('handles mod versions with no dependencies gracefully', function () {
    $serviceSpy = $this->spy(DependencyVersionService::class);

    $modVersion = ModVersion::factory()->create(['version' => '1.0.0']);

    // Check that the service was called and that no resolved dependencies were created.
    $serviceSpy->shouldHaveReceived('resolve');
    expect(ModResolvedDependency::where('mod_version_id', $modVersion->id)->exists())->toBeFalse();
});

it('resolves the correct versions with a complex semver constraint', function () {
    $modVersion = ModVersion::factory()->create(['version' => '1.0.0']);

    $dependentMod = Mod::factory()->create();
    $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0']);
    $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.2.0']);
    $dependentVersion3 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.5.0']);
    $dependentVersion4 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0']);
    $dependentVersion5 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.5.0']);

    // Create a complex SemVer constraint
    ModDependency::factory()->recycle([$modVersion, $dependentMod])->create([
        'constraint' => '>1.0 <2.0 || >=2.5.0 <3.0', // Should resolve to dependentVersion2, dependentVersion3, and dependentVersion5
    ]);

    $resolvedDependencies = ModResolvedDependency::where('mod_version_id', $modVersion->id)->pluck('resolved_mod_version_id');

    expect($resolvedDependencies)->toContain($dependentVersion2->id)
        ->toContain($dependentVersion3->id)
        ->toContain($dependentVersion5->id)
        ->not->toContain($dependentVersion1->id)
        ->not->toContain($dependentVersion4->id);
});

it('resolves overlapping version constraints from multiple dependencies correctly', function () {
    $modVersion = ModVersion::factory()->create(['version' => '1.0.0']);

    $dependentMod1 = Mod::factory()->create();
    $dependentVersion1_1 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '1.0.0']);
    $dependentVersion1_2 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '1.5.0']);

    $dependentMod2 = Mod::factory()->create();
    $dependentVersion2_1 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.0.0']);
    $dependentVersion2_2 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.5.0']);

    // Create two dependencies with overlapping constraints
    ModDependency::factory()->recycle([$modVersion, $dependentMod1])->create([
        'constraint' => '>=1.0 <2.0', // Matches both versions of dependentMod1
    ]);

    ModDependency::factory()->recycle([$modVersion, $dependentMod2])->create([
        'constraint' => '>=1.5.0 <2.0.0', // Matches only the second version of dependentMod2
    ]);

    $resolvedDependencies = ModResolvedDependency::where('mod_version_id', $modVersion->id)->get();

    expect($resolvedDependencies->pluck('resolved_mod_version_id'))
        ->toContain($dependentVersion1_1->id)
        ->toContain($dependentVersion1_2->id)
        ->toContain($dependentVersion2_2->id)
        ->not->toContain($dependentVersion2_1->id);
});

it('handles the case where a dependent mod has no versions available', function () {
    $modVersion = ModVersion::factory()->create(['version' => '1.0.0']);
    $dependentMod = Mod::factory()->create();

    // Create a dependency where the dependent mod has no versions.
    ModDependency::factory()->recycle([$modVersion, $dependentMod])->create([
        'constraint' => '>=1.0.0',
    ]);

    // Verify that no versions were resolved.
    expect(ModResolvedDependency::where('mod_version_id', $modVersion->id)->exists())->toBeFalse();
});

it('handles a large number of versions efficiently', function () {
    $startTime = microtime(true);
    $versionCount = 100;

    $dependentMod = Mod::factory()->create();
    for ($i = 0; $i < $versionCount; $i++) {
        ModVersion::factory()->recycle($dependentMod)->create(['version' => "1.0.$i"]);
    }

    // Create a mod and mod version, and then create a dependency for all versions of the dependent mod.
    $modVersion = ModVersion::factory()->create();
    ModDependency::factory()->recycle([$modVersion, $dependentMod])->create([
        'constraint' => '>=1.0.0',
    ]);

    $executionTime = microtime(true) - $startTime;

    // Verify that all versions were resolved and that the execution time is reasonable.
    expect(ModResolvedDependency::where('mod_version_id', $modVersion->id)->count())->toBe($versionCount)
        ->and($executionTime)->toBeLessThan(5); // Arbitrarily picked out of my ass.
})->skip('This is a performance test and is skipped by default. It will probably fail.');

it('calls DependencyVersionService when a Mod is updated', function () {
    $mod = Mod::factory()->create();
    ModVersion::factory(2)->recycle($mod)->create();

    $serviceSpy = $this->spy(DependencyVersionService::class);

    $mod->update(['name' => 'New Mod Name']);

    $mod->refresh();

    expect($mod->versions)->toHaveCount(2);
    foreach ($mod->versions as $modVersion) {
        $serviceSpy->shouldReceive('resolve')->with($modVersion);
    }
});

it('calls DependencyVersionService when a Mod is deleted', function () {
    $mod = Mod::factory()->create();
    ModVersion::factory(2)->recycle($mod)->create();

    $serviceSpy = $this->spy(DependencyVersionService::class);

    $mod->delete();

    $mod->refresh();

    expect($mod->versions)->toHaveCount(2);
    foreach ($mod->versions as $modVersion) {
        $serviceSpy->shouldReceive('resolve')->with($modVersion);
    }
});

it('calls DependencyVersionService when a ModVersion is updated', function () {
    $modVersion = ModVersion::factory()->create();

    $serviceSpy = $this->spy(DependencyVersionService::class);

    $modVersion->update(['version' => '1.1.0']);

    $serviceSpy->shouldHaveReceived('resolve');
});

it('calls DependencyVersionService when a ModVersion is deleted', function () {
    $modVersion = ModVersion::factory()->create();

    $serviceSpy = $this->spy(DependencyVersionService::class);

    $modVersion->delete();

    $serviceSpy->shouldHaveReceived('resolve');
});

it('calls DependencyVersionService when a ModDependency is updated', function () {
    $modVersion = ModVersion::factory()->create(['version' => '1.0.0']);
    $dependentMod = Mod::factory()->create();
    $modDependency = ModDependency::factory()->recycle([$modVersion, $dependentMod])->create([
        'constraint' => '^1.0',
    ]);

    $serviceSpy = $this->spy(DependencyVersionService::class);

    $modDependency->update(['constraint' => '^2.0']);

    $serviceSpy->shouldHaveReceived('resolve');
});

it('calls DependencyVersionService when a ModDependency is deleted', function () {
    $modVersion = ModVersion::factory()->create(['version' => '1.0.0']);
    $dependentMod = Mod::factory()->create();
    $modDependency = ModDependency::factory()->recycle([$modVersion, $dependentMod])->create([
        'constraint' => '^1.0',
    ]);

    $serviceSpy = $this->spy(DependencyVersionService::class);

    $modDependency->delete();

    $serviceSpy->shouldHaveReceived('resolve');
});

it('displays the latest resolved dependencies on the mod detail page', function () {
    $dependentMod1 = Mod::factory()->create();
    $dependentMod1Version1 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '1.0.0']);
    $dependentMod1Version2 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '2.0.0']);

    $dependentMod2 = Mod::factory()->create();
    $dependentMod2Version1 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.0.0']);
    $dependentMod2Version2 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.1.0']);
    $dependentMod2Version3 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.2.0']);
    $dependentMod2Version4 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.2.1']);

    $mod = Mod::factory()->create();
    $mainModVersion = ModVersion::factory()->recycle($mod)->create();

    ModDependency::factory()->recycle([$mainModVersion, $dependentMod1])->create(['constraint' => '>=1.0.0']);
    ModDependency::factory()->recycle([$mainModVersion, $dependentMod2])->create(['constraint' => '>=1.0.0']);

    $mainModVersion->load('latestResolvedDependencies');

    expect($mainModVersion->latestResolvedDependencies)->toHaveCount(2)
        ->and($mainModVersion->latestResolvedDependencies->pluck('version'))
        ->toContain($dependentMod1Version2->version) // Latest version of dependentMod1
        ->toContain($dependentMod2Version4->version); // Latest version of dependentMod2

    $response = $this->get(route('mod.show', ['mod' => $mod->id, 'slug' => $mod->slug]));

    $response->assertSeeInOrder(explode(' ', __('Dependencies: ')."$dependentMod1->name ($dependentMod1Version2->version)"));
    $response->assertSeeInOrder(explode(' ', __('Dependencies: ')."$dependentMod2->name ($dependentMod2Version4->version)"));
});
