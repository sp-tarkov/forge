<?php

use App\Exceptions\CircularDependencyException;
use App\Models\Mod;
use App\Models\ModDependency;
use App\Models\ModVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves mod version dependency when mod version is created', function () {
    $modA = Mod::factory()->create(['name' => 'Mod A']);
    $modB = Mod::factory()->create(['name' => 'Mod B']);

    // Create versions for Mod B
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.0.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.1']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '2.0.0']);

    // Create versions for Mod A that depends on Mod B
    $modAv1 = ModVersion::factory()->create(['mod_id' => $modA->id, 'version' => '1.0.0']);
    $modDependency = ModDependency::factory()->recycle([$modAv1, $modB])->create([
        'version_constraint' => '^1.0.0',
    ]);

    $modDependency->refresh();

    expect($modDependency->resolvedVersion->version)->toBe('1.1.1');
});

it('resolves mod version dependency when mod version is updated', function () {
    $modA = Mod::factory()->create(['name' => 'Mod A']);
    $modB = Mod::factory()->create(['name' => 'Mod B']);

    // Create versions for Mod B
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.0.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.0']);
    $modBv3 = ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.1']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '2.0.0']);

    // Create versions for Mod A that depends on Mod B
    $modAv1 = ModVersion::factory()->create(['mod_id' => $modA->id, 'version' => '1.0.0']);
    $modDependency = ModDependency::factory()->recycle([$modAv1, $modB])->create([
        'version_constraint' => '^1.0.0',
    ]);

    $modDependency->refresh();
    expect($modDependency->resolvedVersion->version)->toBe('1.1.1');

    // Update the mod B version
    $modBv3->update(['version' => '1.1.2']);

    $modDependency->refresh();
    expect($modDependency->resolvedVersion->version)->toBe('1.1.2');
});

it('resolves mod version dependency when mod version is deleted', function () {
    $modA = Mod::factory()->create(['name' => 'Mod A']);
    $modB = Mod::factory()->create(['name' => 'Mod B']);

    // Create versions for Mod B
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.0.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.0']);
    $modBv3 = ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.1']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '2.0.0']);

    // Create versions for Mod A that depends on Mod B
    $modAv1 = ModVersion::factory()->create(['mod_id' => $modA->id, 'version' => '1.0.0']);
    $modDependency = ModDependency::factory()->recycle([$modAv1, $modB])->create([
        'version_constraint' => '^1.0.0',
    ]);

    $modDependency->refresh();
    expect($modDependency->resolvedVersion->version)->toBe('1.1.1');

    // Update the mod B version
    $modBv3->delete();

    $modDependency->refresh();
    expect($modDependency->resolvedVersion->version)->toBe('1.1.0');
});

it('resolves mod version dependency after semantic version constraint is updated', function () {
    $modA = Mod::factory()->create(['name' => 'Mod A']);
    $modB = Mod::factory()->create(['name' => 'Mod B']);

    // Create versions for Mod B
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.0.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.1']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '2.0.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '2.0.1']);

    // Create versions for Mod A that depends on Mod B
    $modAv1 = ModVersion::factory()->create(['mod_id' => $modA->id, 'version' => '1.0.0']);
    $modDependency = ModDependency::factory()->recycle([$modAv1, $modB])->create([
        'version_constraint' => '^1.0.0',
    ]);

    $modDependency->refresh();
    expect($modDependency->resolvedVersion->version)->toBe('1.1.1');

    // Update the dependency version constraint
    $modDependency->update(['version_constraint' => '^2.0.0']);

    $modDependency->refresh();
    expect($modDependency->resolvedVersion->version)->toBe('2.0.1');
});

it('resolves mod version dependency with exact semantic version constraint', function () {
    $modA = Mod::factory()->create(['name' => 'Mod A']);
    $modB = Mod::factory()->create(['name' => 'Mod B']);

    // Create versions for Mod B
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.0.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.1']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '2.0.0']);

    // Create versions for Mod A that depends on Mod B
    $modAv1 = ModVersion::factory()->create(['mod_id' => $modA->id, 'version' => '1.0.0']);
    $modDependency = ModDependency::factory()->recycle([$modAv1, $modB])->create([
        'version_constraint' => '1.1.0',
    ]);

    $modDependency->refresh();
    expect($modDependency->resolvedVersion->version)->toBe('1.1.0');
});

it('resolves mod version dependency with complex semantic version constraint', function () {
    $modA = Mod::factory()->create(['name' => 'Mod A']);
    $modB = Mod::factory()->create(['name' => 'Mod B']);

    // Create versions for Mod B
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.0.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.1']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.2.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.2.1']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '2.0.0']);

    // Create versions for Mod A that depends on Mod B
    $modAv1 = ModVersion::factory()->create(['mod_id' => $modA->id, 'version' => '1.0.0']);
    $modDependency = ModDependency::factory()->recycle([$modAv1, $modB])->create([
        'version_constraint' => '>=1.0.0 <2.0.0',
    ]);

    $modDependency->refresh();
    expect($modDependency->resolvedVersion->version)->toBe('1.2.1');

    $modDependency->update(['version_constraint' => '1.0.0 || >=1.1.0 <1.2.0']);

    $modDependency->refresh();
    expect($modDependency->resolvedVersion->version)->toBe('1.1.1');
});

it('resolves null when no mod versions are available', function () {
    $modA = Mod::factory()->create(['name' => 'Mod A']);
    $modB = Mod::factory()->create(['name' => 'Mod B']);

    // Create version for Mod A that has no resolvable dependency
    $modAv1 = ModVersion::factory()->create(['mod_id' => $modA->id, 'version' => '1.0.0']);
    $modDependency = ModDependency::factory()->recycle([$modAv1, $modB])->create([
        'version_constraint' => '^1.0.0',
    ]);

    $modDependency->refresh();
    expect($modDependency->resolved_version_id)->toBeNull();
});

it('resolves null when no mod versions match against semantic version constraint', function () {
    $modA = Mod::factory()->create(['name' => 'Mod A']);
    $modB = Mod::factory()->create(['name' => 'Mod B']);

    // Create versions for Mod B
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.0.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '2.0.0']);

    // Create version for Mod A that has no resolvable dependency
    $modAv1 = ModVersion::factory()->create(['mod_id' => $modA->id, 'version' => '1.0.0']);
    $modDependency = ModDependency::factory()->recycle([$modAv1, $modB])->create([
        'version_constraint' => '~1.2.0',
    ]);

    $modDependency->refresh();
    expect($modDependency->resolved_version_id)->toBeNull();
});

it('resolves multiple dependencies', function () {
    $modA = Mod::factory()->create(['name' => 'Mod A']);
    $modB = Mod::factory()->create(['name' => 'Mod B']);
    $modC = Mod::factory()->create(['name' => 'Mod C']);

    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.0.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.0']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '1.1.1']);
    ModVersion::factory()->create(['mod_id' => $modB->id, 'version' => '2.0.0']);

    ModVersion::factory()->create(['mod_id' => $modC->id, 'version' => '1.0.0']);
    ModVersion::factory()->create(['mod_id' => $modC->id, 'version' => '1.1.0']);
    ModVersion::factory()->create(['mod_id' => $modC->id, 'version' => '1.1.1']);
    ModVersion::factory()->create(['mod_id' => $modC->id, 'version' => '2.0.0']);

    // Creating a version for Mod A that depends on Mod B and Mod C
    $modAv1 = ModVersion::factory()->create(['mod_id' => $modA->id, 'version' => '1.0.0']);

    $modDependencyB = ModDependency::factory()->recycle([$modAv1, $modB])->create([
        'version_constraint' => '^1.0.0',
    ]);
    $modDependencyC = ModDependency::factory()->recycle([$modAv1, $modC])->create([
        'version_constraint' => '^1.0.0',
    ]);

    $modDependencyB->refresh();
    expect($modDependencyB->resolvedVersion->version)->toBe('1.1.1');

    $modDependencyC->refresh();
    expect($modDependencyC->resolvedVersion->version)->toBe('1.1.1');
});

it('throws exception when there is a circular version dependency', function () {
    $modA = Mod::factory()->create(['name' => 'Mod A']);
    $modAv1 = ModVersion::factory()->recycle($modA)->create(['version' => '1.0.0']);

    $modB = Mod::factory()->create(['name' => 'Mod B']);
    $modBv1 = ModVersion::factory()->recycle($modB)->create(['version' => '1.0.0']);

    $modDependencyAtoB = ModDependency::factory()->recycle([$modAv1, $modB])->create([
        'version_constraint' => '1.0.0',
    ]);

    // Create circular dependencies
    $modDependencyBtoA = ModDependency::factory()->recycle([$modBv1, $modA])->create([
        'version_constraint' => '1.0.0',
    ]);
})->throws(CircularDependencyException::class);
