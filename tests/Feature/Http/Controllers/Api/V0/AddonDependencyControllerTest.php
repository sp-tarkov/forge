<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Dependency;
use App\Models\DependencyResolved;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->sptVersion = SptVersion::factory()->create([
        'version' => '3.9.0',
        'publish_date' => now()->subDay(),
    ]);
});

/**
 * Create a published addon version.
 */
function createPublishedAddonVersion(Addon $addon, string $version): AddonVersion
{
    return AddonVersion::factory()->create([
        'addon_id' => $addon->id,
        'version' => $version,
        'published_at' => now()->subDay(),
        'disabled' => false,
    ]);
}

/**
 * Create a published mod version synced to the given SPT version.
 */
function createSptSyncedDependencyVersion(Mod $mod, string $version, SptVersion $sptVersion): ModVersion
{
    $modVersion = ModVersion::factory()->create([
        'mod_id' => $mod->id,
        'version' => $version,
        'published_at' => now()->subDay(),
        'disabled' => false,
    ]);
    $modVersion->sptVersions()->sync([$sptVersion->id]);

    return $modVersion;
}

/**
 * Create an addon version dependency with resolved rows for the given resolved mod versions.
 */
function createResolvedAddonDependency(AddonVersion $addonVersion, Mod $dependentMod, string $constraint, ModVersion ...$resolvedVersions): Dependency
{
    $dependency = Dependency::factory()->create([
        'dependable_type' => AddonVersion::class,
        'dependable_id' => $addonVersion->id,
        'dependent_mod_id' => $dependentMod->id,
        'constraint' => $constraint,
    ]);

    foreach ($resolvedVersions as $resolvedVersion) {
        DependencyResolved::factory()->create([
            'dependable_type' => AddonVersion::class,
            'dependable_id' => $addonVersion->id,
            'dependency_id' => $dependency->id,
            'resolved_mod_version_id' => $resolvedVersion->id,
        ]);
    }

    return $dependency;
}

/**
 * Create a mod version dependency with resolved rows for the given resolved mod versions.
 */
function createResolvedNestedDependency(ModVersion $dependableVersion, Mod $dependentMod, string $constraint, ModVersion ...$resolvedVersions): Dependency
{
    $dependency = Dependency::factory()->create([
        'dependable_id' => $dependableVersion->id,
        'dependent_mod_id' => $dependentMod->id,
        'constraint' => $constraint,
    ]);

    foreach ($resolvedVersions as $resolvedVersion) {
        DependencyResolved::factory()->create([
            'dependable_id' => $dependableVersion->id,
            'dependency_id' => $dependency->id,
            'resolved_mod_version_id' => $resolvedVersion->id,
        ]);
    }

    return $dependency;
}

describe('resolve', function (): void {
    describe('parameter validation', function (): void {
        it('returns error when no parameters are provided', function (): void {
            $response = $this->getJson('/api/v0/addons/dependencies');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'addons' and 'spt_version' parameters.",
                ]);
        });

        it('returns error when spt_version is missing', function (): void {
            $response = $this->getJson('/api/v0/addons/dependencies?addons=5:1.2.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'addons' and 'spt_version' parameters.",
                ]);
        });

        it('returns a 400 instead of a 500 when addons is provided as an array', function (): void {
            $response = $this->getJson('/api/v0/addons/dependencies?addons[]=5:1.2.0&spt_version=3.9.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "Invalid format for 'addons' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either an addon_id (numeric) or slug (string)",
                ]);
        });

        it('returns a 400 instead of a 500 when spt_version is provided as an array', function (): void {
            $response = $this->getJson('/api/v0/addons/dependencies?addons=5:1.2.0&spt_version[]=3.9.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'addons' and 'spt_version' parameters.",
                ]);
        });

        it('returns error when spt_version does not exist', function (): void {
            $response = $this->getJson('/api/v0/addons/dependencies?addons=5:1.2.0&spt_version=99.99.99');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'SPT version not found or not published.',
                ]);
        });

        it('returns error when invalid format is provided', function (): void {
            $response = $this->getJson('/api/v0/addons/dependencies?addons=abc&spt_version=3.9.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "Invalid format for 'addons' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either an addon_id (numeric) or slug (string)",
                ]);
        });

        it('returns an empty object when non-existent addon:version pairs are provided', function (): void {
            $response = $this->getJson('/api/v0/addons/dependencies?addons=99999:1.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            expect($response->content())->toContain('"data":{}');
        });
    });

    describe('grouping', function (): void {
        it('keys groups by addon id and slug pairs as sent', function (): void {
            $addon1 = Addon::factory()->create();
            createPublishedAddonVersion($addon1, '1.0.0');

            $addon2 = Addon::factory()->create(['slug' => 'my-test-addon']);
            createPublishedAddonVersion($addon2, '2.0.0');

            $response = $this->getJson(sprintf('/api/v0/addons/dependencies?addons=%d:1.0.0,my-test-addon:2.0.0&spt_version=3.9.0', $addon1->id));

            $response->assertSuccessful();

            expect($response->json('data'))->toBe([
                sprintf('%d:1.0.0', $addon1->id) => [],
                'my-test-addon:2.0.0' => [],
            ]);
        });

        it('resolves mod dependencies for an addon version with the documented node shape', function (): void {
            $addon = Addon::factory()->create(['slug' => 'my-addon']);
            $addonVersion = createPublishedAddonVersion($addon, '1.0.0');

            $dependencyMod = Mod::factory()->create([
                'name' => 'Dependency Mod',
                'guid' => 'com.example.dependency',
            ]);
            $dependencyVersion = createSptSyncedDependencyVersion($dependencyMod, '2.0.0', $this->sptVersion);

            createResolvedAddonDependency($addonVersion, $dependencyMod, '^2.0.0', $dependencyVersion);

            $response = $this->getJson('/api/v0/addons/dependencies?addons=my-addon:1.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            $group = $response->json('data')['my-addon:1.0.0'];

            expect($group)->toHaveCount(1)
                ->and(array_keys($group[0]))
                ->toBe(['id', 'guid', 'name', 'slug', 'latest_compatible_version', 'conflict', 'dependencies'])
                ->and($group[0]['name'])->toBe('Dependency Mod')
                ->and($group[0]['guid'])->toBe('com.example.dependency')
                ->and($group[0]['conflict'])->toBeFalse()
                ->and(array_keys($group[0]['latest_compatible_version']))
                ->toBe(['id', 'version', 'link', 'content_length', 'fika_compatibility'])
                ->and($group[0]['latest_compatible_version']['version'])->toBe('2.0.0');
        });

        it('recurses into the mod dependencies of resolved addon dependencies', function (): void {
            $addon = Addon::factory()->create(['slug' => 'my-addon']);
            $addonVersion = createPublishedAddonVersion($addon, '1.0.0');

            $level1Mod = Mod::factory()->create(['name' => 'Level 1 Dependency']);
            $level1Version = createSptSyncedDependencyVersion($level1Mod, '1.0.0', $this->sptVersion);

            $level2Mod = Mod::factory()->create(['name' => 'Level 2 Dependency']);
            $level2Version = createSptSyncedDependencyVersion($level2Mod, '1.0.0', $this->sptVersion);

            createResolvedAddonDependency($addonVersion, $level1Mod, '*', $level1Version);
            createResolvedNestedDependency($level1Version, $level2Mod, '*', $level2Version);

            $response = $this->getJson('/api/v0/addons/dependencies?addons=my-addon:1.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            $group = $response->json('data')['my-addon:1.0.0'];

            expect($group)->toHaveCount(1)
                ->and($group[0]['name'])->toBe('Level 1 Dependency')
                ->and($group[0]['dependencies'])->toHaveCount(1)
                ->and($group[0]['dependencies'][0]['name'])->toBe('Level 2 Dependency');
        });

        it('shows a shared mod dependency identically in every requiring addon group', function (): void {
            $addon1 = Addon::factory()->create(['slug' => 'addon-one']);
            $addon1Version = createPublishedAddonVersion($addon1, '1.0.0');

            $addon2 = Addon::factory()->create(['slug' => 'addon-two']);
            $addon2Version = createPublishedAddonVersion($addon2, '2.0.0');

            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);
            $sharedDepVersion = createSptSyncedDependencyVersion($sharedDep, '1.5.0', $this->sptVersion);

            createResolvedAddonDependency($addon1Version, $sharedDep, '^1.0.0', $sharedDepVersion);
            createResolvedAddonDependency($addon2Version, $sharedDep, '^1.0.0', $sharedDepVersion);

            $response = $this->getJson('/api/v0/addons/dependencies?addons=addon-one:1.0.0,addon-two:2.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            $data = $response->json('data');

            expect($data['addon-one:1.0.0'])->toHaveCount(1)
                ->and($data['addon-one:1.0.0'])->toBe($data['addon-two:2.0.0'])
                ->and($data['addon-one:1.0.0'][0]['name'])->toBe('Shared Dependency')
                ->and($data['addon-one:1.0.0'][0]['latest_compatible_version']['version'])->toBe('1.5.0');
        });
    });

    describe('spt filtering', function (): void {
        it('demotes to an older version compatible with the requested SPT version', function (): void {
            $newerSptVersion = SptVersion::factory()->create([
                'version' => '4.0.0',
                'publish_date' => now()->subDay(),
            ]);

            $addon = Addon::factory()->create(['slug' => 'my-addon']);
            $addonVersion = createPublishedAddonVersion($addon, '1.0.0');

            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $compatibleVersion = createSptSyncedDependencyVersion($dependencyMod, '1.5.0', $this->sptVersion);
            $incompatibleVersion = createSptSyncedDependencyVersion($dependencyMod, '2.0.0', $newerSptVersion);

            createResolvedAddonDependency($addonVersion, $dependencyMod, '^1.0.0', $compatibleVersion, $incompatibleVersion);

            $response = $this->getJson('/api/v0/addons/dependencies?addons=my-addon:1.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            $group = $response->json('data')['my-addon:1.0.0'];

            expect($group)->toHaveCount(1)
                ->and($group[0]['latest_compatible_version']['version'])->toBe('1.5.0');
        });

        it('returns a null version node when no dependency version is compatible with the SPT version', function (): void {
            $newerSptVersion = SptVersion::factory()->create([
                'version' => '4.0.0',
                'publish_date' => now()->subDay(),
            ]);

            $addon = Addon::factory()->create(['slug' => 'my-addon']);
            $addonVersion = createPublishedAddonVersion($addon, '1.0.0');

            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $incompatibleVersion = createSptSyncedDependencyVersion($dependencyMod, '1.0.0', $newerSptVersion);

            createResolvedAddonDependency($addonVersion, $dependencyMod, '^1.0.0', $incompatibleVersion);

            $response = $this->getJson('/api/v0/addons/dependencies?addons=my-addon:1.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            $group = $response->json('data')['my-addon:1.0.0'];

            expect($group)->toHaveCount(1)
                ->and($group[0]['name'])->toBe('Dependency Mod')
                ->and($group[0]['latest_compatible_version'])->toBeNull()
                ->and($group[0]['conflict'])->toBeFalse()
                ->and($group[0]['dependencies'])->toBe([]);
        });
    });
});
