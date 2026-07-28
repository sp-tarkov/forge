<?php

declare(strict_types=1);

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
 * Create a published mod version synced to the given SPT version.
 */
function createSyncedModVersion(Mod $mod, string $version, SptVersion $sptVersion): ModVersion
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
 * Create a dependency with resolved rows linking a dependable mod version to resolved dependency versions.
 */
function createResolvedDependency(ModVersion $dependableVersion, Mod $dependentMod, string $constraint, ModVersion ...$resolvedVersions): Dependency
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
            $response = $this->getJson('/api/v0/mods/dependencies');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'mods' and 'spt_version' parameters.",
                ]);
        });

        it('returns error when spt_version is missing', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies?mods=5:1.2.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'mods' and 'spt_version' parameters.",
                ]);
        });

        it('returns error when mods is missing', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies?spt_version=3.9.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'mods' and 'spt_version' parameters.",
                ]);
        });

        it('returns error when spt_version is an empty string', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies?mods=5:1.2.0&spt_version=');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'mods' and 'spt_version' parameters.",
                ]);
        });

        it('returns error when only whitespace is provided for mods', function (): void {
            $url = '/api/v0/mods/dependencies?spt_version=3.9.0&mods='.urlencode('   ');
            $response = $this->getJson($url);

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'mods' and 'spt_version' parameters.",
                ]);
        });

        it('returns a 400 instead of a 500 when mods is provided as an array', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies?mods[]=5:1.2.0&mods[]=6:1.0.0&spt_version=3.9.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either a mod_id (numeric) or GUID (string)",
                ]);
        });

        it('returns a 400 instead of a 500 when spt_version is provided as an array', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies?mods=5:1.2.0&spt_version[]=3.9.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'mods' and 'spt_version' parameters.",
                ]);
        });

        it('returns error when spt_version does not exist', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies?mods=5:1.2.0&spt_version=99.99.99');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'SPT version not found or not published.',
                ]);
        });

        it('returns error when spt_version is not published', function (): void {
            SptVersion::factory()->create([
                'version' => '4.5.0',
                'publish_date' => null,
            ]);

            $response = $this->getJson('/api/v0/mods/dependencies?mods=5:1.2.0&spt_version=4.5.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'SPT version not found or not published.',
                ]);
        });

        it('returns error when invalid format is provided', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies?mods=abc,xyz&spt_version=3.9.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either a mod_id (numeric) or GUID (string)",
                ]);
        });

        it('returns error when missing colon separator', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies?mods=5&spt_version=3.9.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either a mod_id (numeric) or GUID (string)",
                ]);
        });

        it('returns an empty object when non-existent mod:version pairs are provided', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies?mods=99999:1.0.0,88888:2.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            expect($response->content())->toContain('"data":{}');
        });

        it('returns an empty object when non-existent GUID:version pairs are provided', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies?mods=com.nonexistent.mod:1.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            expect($response->content())->toContain('"data":{}');
        });
    });

    describe('grouping', function (): void {
        it('keys each group by the queried pair as sent', function (): void {
            $mod = Mod::factory()->create(['guid' => 'com.example.testmod']);
            createSyncedModVersion($mod, '1.0.0', $this->sptVersion);

            $response = $this->getJson('/api/v0/mods/dependencies?mods=com.example.testmod:1.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            expect($response->json('data'))->toBe(['com.example.testmod:1.0.0' => []]);
        });

        it('preserves the casing of a mixed-case GUID key', function (): void {
            $mod = Mod::factory()->create(['guid' => 'com.example.testmod']);
            createSyncedModVersion($mod, '1.0.0', $this->sptVersion);

            $response = $this->getJson('/api/v0/mods/dependencies?mods=Com.Example.TestMod:1.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            expect($response->json('data'))->toBe(['Com.Example.TestMod:1.0.0' => []]);
        });

        it('accepts mixed mod_id and GUID identifiers in same request', function (): void {
            $mod1 = Mod::factory()->create(['guid' => 'com.example.mod1']);
            createSyncedModVersion($mod1, '1.0.0', $this->sptVersion);

            $mod2 = Mod::factory()->create(['guid' => 'com.example.mod2']);
            createSyncedModVersion($mod2, '2.0.0', $this->sptVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0,com.example.mod2:2.0.0&spt_version=3.9.0', $mod1->id));

            $response->assertSuccessful();

            expect($response->json('data'))->toBe([
                sprintf('%d:1.0.0', $mod1->id) => [],
                'com.example.mod2:2.0.0' => [],
            ]);
        });

        it('returns identical groups when the same version is queried by id and GUID', function (): void {
            $mod = Mod::factory()->create(['guid' => 'com.example.testmod']);
            $modVersion = createSyncedModVersion($mod, '1.0.0', $this->sptVersion);

            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $dependencyVersion = createSyncedModVersion($dependencyMod, '2.0.0', $this->sptVersion);
            createResolvedDependency($modVersion, $dependencyMod, '^2.0.0', $dependencyVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0,com.example.testmod:1.0.0&spt_version=3.9.0', $mod->id));

            $response->assertSuccessful();

            $data = $response->json('data');

            expect($data)->toHaveKeys([sprintf('%d:1.0.0', $mod->id), 'com.example.testmod:1.0.0'])
                ->and($data[sprintf('%d:1.0.0', $mod->id)])->toBe($data['com.example.testmod:1.0.0'])
                ->and($data['com.example.testmod:1.0.0'][0]['name'])->toBe('Dependency Mod');
        });

        it('omits unresolved pairs while keeping resolved dependency-free pairs', function (): void {
            $mod = Mod::factory()->create();
            createSyncedModVersion($mod, '1.0.0', $this->sptVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0,99999:5.0.0&spt_version=3.9.0', $mod->id));

            $response->assertSuccessful();

            expect($response->json('data'))->toBe([sprintf('%d:1.0.0', $mod->id) => []]);
        });
    });

    describe('dependency resolution', function (): void {
        it('resolves dependencies when using GUID identifier', function (): void {
            $mainMod = Mod::factory()->create([
                'name' => 'Main Mod',
                'guid' => 'com.example.mainmod',
            ]);
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $dependencyMod = Mod::factory()->create([
                'name' => 'Dependency Mod',
                'guid' => 'com.example.dependency',
            ]);
            $dependencyModVersion = createSyncedModVersion($dependencyMod, '2.0.0', $this->sptVersion);

            createResolvedDependency($mainModVersion, $dependencyMod, '^2.0.0', $dependencyModVersion);

            $response = $this->getJson('/api/v0/mods/dependencies?mods=com.example.mainmod:1.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            $group = $response->json('data')['com.example.mainmod:1.0.0'];

            expect($group)->toHaveCount(1)
                ->and($group[0]['name'])->toBe('Dependency Mod')
                ->and($group[0]['guid'])->toBe('com.example.dependency')
                ->and($group[0]['latest_compatible_version']['version'])->toBe('2.0.0')
                ->and($group[0]['dependencies'])->toBe([]);
        });

        it('returns only the documented fields for the latest compatible version', function (): void {
            $mainMod = Mod::factory()->create(['guid' => 'com.example.mainmod']);
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $dependencyMod = Mod::factory()->create(['guid' => 'com.example.dependency']);
            $dependencyModVersion = createSyncedModVersion($dependencyMod, '2.0.0', $this->sptVersion);

            createResolvedDependency($mainModVersion, $dependencyMod, '^2.0.0', $dependencyModVersion);

            $response = $this->getJson('/api/v0/mods/dependencies?mods=com.example.mainmod:1.0.0&spt_version=3.9.0');

            $response->assertSuccessful();

            $group = $response->json('data')['com.example.mainmod:1.0.0'];

            expect(array_keys($group[0]))
                ->toBe(['id', 'guid', 'name', 'slug', 'latest_compatible_version', 'conflict', 'dependencies'])
                ->and(array_keys($group[0]['latest_compatible_version']))
                ->toBe(['id', 'version', 'link', 'content_length', 'fika_compatibility']);
        });

        it('resolves dependencies for a single mod version queried by id', function (): void {
            $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $dependencyModVersion = createSyncedModVersion($dependencyMod, '2.0.0', $this->sptVersion);

            createResolvedDependency($mainModVersion, $dependencyMod, '^2.0.0', $dependencyModVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $mainMod->id));

            $response->assertSuccessful();

            $group = $response->json('data')[sprintf('%d:1.0.0', $mainMod->id)];

            expect($group)->toHaveCount(1)
                ->and($group[0]['name'])->toBe('Dependency Mod')
                ->and($group[0]['latest_compatible_version']['version'])->toBe('2.0.0');
        });

        it('resolves nested dependencies recursively', function (): void {
            $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $level1Mod = Mod::factory()->create(['name' => 'Level 1 Dependency']);
            $level1Version = createSyncedModVersion($level1Mod, '1.0.0', $this->sptVersion);

            $level2Mod = Mod::factory()->create(['name' => 'Level 2 Dependency']);
            $level2Version = createSyncedModVersion($level2Mod, '1.0.0', $this->sptVersion);

            createResolvedDependency($mainModVersion, $level1Mod, '*', $level1Version);
            createResolvedDependency($level1Version, $level2Mod, '*', $level2Version);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $mainMod->id));

            $response->assertSuccessful();

            $group = $response->json('data')[sprintf('%d:1.0.0', $mainMod->id)];

            expect($group)->toHaveCount(1)
                ->and($group[0]['name'])->toBe('Level 1 Dependency')
                ->and($group[0]['dependencies'])->toHaveCount(1)
                ->and($group[0]['dependencies'][0]['name'])->toBe('Level 2 Dependency')
                ->and($group[0]['dependencies'][0]['dependencies'])->toBe([]);
        });

        it('shows a shared dependency identically in every requiring group', function (): void {
            $mod1 = Mod::factory()->create(['name' => 'Mod 1']);
            $mod1Version = createSyncedModVersion($mod1, '1.0.0', $this->sptVersion);

            $mod2 = Mod::factory()->create(['name' => 'Mod 2']);
            $mod2Version = createSyncedModVersion($mod2, '2.0.0', $this->sptVersion);

            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);
            $sharedDepVersion = createSyncedModVersion($sharedDep, '1.0.0', $this->sptVersion);

            createResolvedDependency($mod1Version, $sharedDep, '*', $sharedDepVersion);
            createResolvedDependency($mod2Version, $sharedDep, '*', $sharedDepVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0,%d:2.0.0&spt_version=3.9.0', $mod1->id, $mod2->id));

            $response->assertSuccessful();

            $data = $response->json('data');
            $group1 = $data[sprintf('%d:1.0.0', $mod1->id)];
            $group2 = $data[sprintf('%d:2.0.0', $mod2->id)];

            expect($group1)->toHaveCount(1)
                ->and($group1)->toBe($group2)
                ->and($group1[0]['name'])->toBe('Shared Dependency')
                ->and($group1[0]['latest_compatible_version']['version'])->toBe('1.0.0')
                ->and($group1[0]['conflict'])->toBeFalse();
        });

        it('flags conflicting constraints and shows each group its own version', function (): void {
            $mod1 = Mod::factory()->create(['name' => 'Queried Mod 1']);
            $mod1Version = createSyncedModVersion($mod1, '1.0.0', $this->sptVersion);

            $mod2 = Mod::factory()->create(['name' => 'Queried Mod 2']);
            $mod2Version = createSyncedModVersion($mod2, '2.0.0', $this->sptVersion);

            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);
            $sharedDepVersion1 = createSyncedModVersion($sharedDep, '1.0.0', $this->sptVersion);
            $sharedDepVersion2 = createSyncedModVersion($sharedDep, '2.0.0', $this->sptVersion);

            createResolvedDependency($mod1Version, $sharedDep, '^1.0.0', $sharedDepVersion1);
            createResolvedDependency($mod2Version, $sharedDep, '^2.0.0', $sharedDepVersion2);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0,%d:2.0.0&spt_version=3.9.0', $mod1->id, $mod2->id));

            $response->assertSuccessful();

            $data = $response->json('data');
            $group1 = $data[sprintf('%d:1.0.0', $mod1->id)];
            $group2 = $data[sprintf('%d:2.0.0', $mod2->id)];

            expect($group1[0]['latest_compatible_version']['version'])->toBe('1.0.0')
                ->and($group1[0]['conflict'])->toBeTrue()
                ->and($group2[0]['latest_compatible_version']['version'])->toBe('2.0.0')
                ->and($group2[0]['conflict'])->toBeTrue();
        });

        it('chooses the highest version satisfying all compatible constraints in every group', function (): void {
            $mod1 = Mod::factory()->create(['name' => 'Queried Mod 1']);
            $mod1Version = createSyncedModVersion($mod1, '1.0.0', $this->sptVersion);

            $mod2 = Mod::factory()->create(['name' => 'Queried Mod 2']);
            $mod2Version = createSyncedModVersion($mod2, '2.0.0', $this->sptVersion);

            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);
            $sharedDepVersion1_0 = createSyncedModVersion($sharedDep, '1.0.0', $this->sptVersion);
            $sharedDepVersion1_5 = createSyncedModVersion($sharedDep, '1.5.0', $this->sptVersion);
            $sharedDepVersion1_8 = createSyncedModVersion($sharedDep, '1.8.0', $this->sptVersion);

            createResolvedDependency($mod1Version, $sharedDep, '^1.0.0', $sharedDepVersion1_0, $sharedDepVersion1_5, $sharedDepVersion1_8);
            createResolvedDependency($mod2Version, $sharedDep, '^1.5.0', $sharedDepVersion1_5, $sharedDepVersion1_8);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0,%d:2.0.0&spt_version=3.9.0', $mod1->id, $mod2->id));

            $response->assertSuccessful();

            $data = $response->json('data');
            $group1 = $data[sprintf('%d:1.0.0', $mod1->id)];
            $group2 = $data[sprintf('%d:2.0.0', $mod2->id)];

            expect($group1[0]['latest_compatible_version']['version'])->toBe('1.8.0')
                ->and($group1[0]['conflict'])->toBeFalse()
                ->and($group2[0]['latest_compatible_version']['version'])->toBe('1.8.0')
                ->and($group2[0]['conflict'])->toBeFalse();
        });

        it('applies conflict detection to nested dependencies', function (): void {
            $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $level1Dep = Mod::factory()->create(['name' => 'Level 1 Dependency']);
            $level1Version = createSyncedModVersion($level1Dep, '1.0.0', $this->sptVersion);

            $nestedDep = Mod::factory()->create(['name' => 'Nested Dependency']);
            $nestedVersion = createSyncedModVersion($nestedDep, '1.0.0', $this->sptVersion);

            createResolvedDependency($mainModVersion, $level1Dep, '*', $level1Version);
            createResolvedDependency($level1Version, $nestedDep, '*', $nestedVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $mainMod->id));

            $response->assertSuccessful();

            $group = $response->json('data')[sprintf('%d:1.0.0', $mainMod->id)];

            expect($group[0]['name'])->toBe('Level 1 Dependency')
                ->and($group[0]['conflict'])->toBeFalse()
                ->and($group[0]['dependencies'][0]['name'])->toBe('Nested Dependency')
                ->and($group[0]['dependencies'][0]['conflict'])->toBeFalse();
        });

        it('keeps shared and unique dependencies attributed to the correct groups', function (): void {
            $mod1 = Mod::factory()->create(['name' => 'Queried Mod 1']);
            $mod1Version = createSyncedModVersion($mod1, '1.0.0', $this->sptVersion);

            $mod2 = Mod::factory()->create(['name' => 'Queried Mod 2']);
            $mod2Version = createSyncedModVersion($mod2, '2.0.0', $this->sptVersion);

            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);
            $sharedDepVersion = createSyncedModVersion($sharedDep, '1.5.0', $this->sptVersion);

            $uniqueDep1 = Mod::factory()->create(['name' => 'Unique Dependency 1']);
            $uniqueDep1Version = createSyncedModVersion($uniqueDep1, '1.0.0', $this->sptVersion);

            $uniqueDep2 = Mod::factory()->create(['name' => 'Unique Dependency 2']);
            $uniqueDep2Version = createSyncedModVersion($uniqueDep2, '2.0.0', $this->sptVersion);

            createResolvedDependency($mod1Version, $sharedDep, '*', $sharedDepVersion);
            createResolvedDependency($mod1Version, $uniqueDep1, '*', $uniqueDep1Version);
            createResolvedDependency($mod2Version, $sharedDep, '*', $sharedDepVersion);
            createResolvedDependency($mod2Version, $uniqueDep2, '*', $uniqueDep2Version);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0,%d:2.0.0&spt_version=3.9.0', $mod1->id, $mod2->id));

            $response->assertSuccessful();

            $data = $response->json('data');
            $group1Names = collect($data[sprintf('%d:1.0.0', $mod1->id)])->pluck('name')->sort()->values()->all();
            $group2Names = collect($data[sprintf('%d:2.0.0', $mod2->id)])->pluck('name')->sort()->values()->all();

            expect($group1Names)->toBe(['Shared Dependency', 'Unique Dependency 1'])
                ->and($group2Names)->toBe(['Shared Dependency', 'Unique Dependency 2']);
        });

        it('returns only the latest compatible version for each dependency', function (): void {
            $mainMod = Mod::factory()->create();
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $resolvedVersion = createSyncedModVersion($dependencyMod, '2.0.0', $this->sptVersion);

            $olderVersion = ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '1.5.0',
                'published_at' => now()->subDays(2),
                'disabled' => true,
            ]);
            $olderVersion->sptVersions()->sync([$this->sptVersion->id]);

            createResolvedDependency($mainModVersion, $dependencyMod, '^2.0.0', $resolvedVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $mainMod->id));

            $response->assertSuccessful();

            $group = $response->json('data')[sprintf('%d:1.0.0', $mainMod->id)];

            expect($group)->toHaveCount(1)
                ->and($group[0]['name'])->toBe('Dependency Mod')
                ->and($group[0]['latest_compatible_version']['version'])->toBe('2.0.0');
        });
    });

    describe('spt filtering', function (): void {
        it('demotes to an older version compatible with the requested SPT version', function (): void {
            $newerSptVersion = SptVersion::factory()->create([
                'version' => '4.0.0',
                'publish_date' => now()->subDay(),
            ]);

            $mainMod = Mod::factory()->create();
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $compatibleVersion = createSyncedModVersion($dependencyMod, '1.5.0', $this->sptVersion);
            $incompatibleVersion = createSyncedModVersion($dependencyMod, '2.0.0', $newerSptVersion);

            createResolvedDependency($mainModVersion, $dependencyMod, '^1.0.0', $compatibleVersion, $incompatibleVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $mainMod->id));

            $response->assertSuccessful();

            $group = $response->json('data')[sprintf('%d:1.0.0', $mainMod->id)];

            expect($group)->toHaveCount(1)
                ->and($group[0]['latest_compatible_version']['version'])->toBe('1.5.0')
                ->and($group[0]['conflict'])->toBeFalse();
        });

        it('returns a null version node when no dependency version is compatible with the SPT version', function (): void {
            $newerSptVersion = SptVersion::factory()->create([
                'version' => '4.0.0',
                'publish_date' => now()->subDay(),
            ]);

            $mainMod = Mod::factory()->create();
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $incompatibleVersion = createSyncedModVersion($dependencyMod, '1.0.0', $newerSptVersion);

            $nestedDep = Mod::factory()->create(['name' => 'Nested Dependency']);
            $nestedVersion = createSyncedModVersion($nestedDep, '1.0.0', $newerSptVersion);

            createResolvedDependency($mainModVersion, $dependencyMod, '^1.0.0', $incompatibleVersion);
            createResolvedDependency($incompatibleVersion, $nestedDep, '*', $nestedVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $mainMod->id));

            $response->assertSuccessful();

            $group = $response->json('data')[sprintf('%d:1.0.0', $mainMod->id)];

            expect($group)->toHaveCount(1)
                ->and($group[0]['name'])->toBe('Dependency Mod')
                ->and($group[0]['latest_compatible_version'])->toBeNull()
                ->and($group[0]['conflict'])->toBeFalse()
                ->and($group[0]['dependencies'])->toBe([]);
        });

        it('flags a null version node with conflict when another queried mod requires an incompatible version', function (): void {
            $newerSptVersion = SptVersion::factory()->create([
                'version' => '4.0.0',
                'publish_date' => now()->subDay(),
            ]);

            $mod1 = Mod::factory()->create(['name' => 'Queried Mod 1']);
            $mod1Version = createSyncedModVersion($mod1, '1.0.0', $this->sptVersion);

            $mod2 = Mod::factory()->create(['name' => 'Queried Mod 2']);
            $mod2Version = createSyncedModVersion($mod2, '2.0.0', $this->sptVersion);

            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);
            $incompatibleV1 = createSyncedModVersion($sharedDep, '1.0.0', $newerSptVersion);
            $compatibleV2 = createSyncedModVersion($sharedDep, '2.1.0', $this->sptVersion);

            createResolvedDependency($mod1Version, $sharedDep, '^1.0.0', $incompatibleV1);
            createResolvedDependency($mod2Version, $sharedDep, '^2.0.0', $compatibleV2);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0,%d:2.0.0&spt_version=3.9.0', $mod1->id, $mod2->id));

            $response->assertSuccessful();

            $data = $response->json('data');
            $group1 = $data[sprintf('%d:1.0.0', $mod1->id)];
            $group2 = $data[sprintf('%d:2.0.0', $mod2->id)];

            expect($group1[0]['latest_compatible_version'])->toBeNull()
                ->and($group1[0]['conflict'])->toBeTrue()
                ->and($group2[0]['latest_compatible_version']['version'])->toBe('2.1.0')
                ->and($group2[0]['conflict'])->toBeTrue();
        });
    });

    describe('global consistency', function (): void {
        it('shows the globally satisfying version in every group even when a local pick is higher', function (): void {
            $mod1 = Mod::factory()->create(['name' => 'Queried Mod 1']);
            $mod1Version = createSyncedModVersion($mod1, '1.0.0', $this->sptVersion);

            $mod2 = Mod::factory()->create(['name' => 'Queried Mod 2']);
            $mod2Version = createSyncedModVersion($mod2, '2.0.0', $this->sptVersion);

            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);
            $v1_0 = createSyncedModVersion($sharedDep, '1.0.0', $this->sptVersion);
            $v1_5_9 = createSyncedModVersion($sharedDep, '1.5.9', $this->sptVersion);
            $v1_9 = createSyncedModVersion($sharedDep, '1.9.0', $this->sptVersion);

            createResolvedDependency($mod1Version, $sharedDep, '^1.0.0', $v1_0, $v1_5_9, $v1_9);
            createResolvedDependency($mod2Version, $sharedDep, '~1.5.0', $v1_5_9);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0,%d:2.0.0&spt_version=3.9.0', $mod1->id, $mod2->id));

            $response->assertSuccessful();

            $data = $response->json('data');
            $group1 = $data[sprintf('%d:1.0.0', $mod1->id)];
            $group2 = $data[sprintf('%d:2.0.0', $mod2->id)];

            expect($group1[0]['latest_compatible_version']['version'])->toBe('1.5.9')
                ->and($group1[0]['conflict'])->toBeFalse()
                ->and($group2[0]['latest_compatible_version']['version'])->toBe('1.5.9')
                ->and($group2[0]['conflict'])->toBeFalse();
        });

        it('shows the same version for a mod appearing top-level in one group and nested in another', function (): void {
            $mod1 = Mod::factory()->create(['name' => 'Queried Mod 1']);
            $mod1Version = createSyncedModVersion($mod1, '1.0.0', $this->sptVersion);

            $mod2 = Mod::factory()->create(['name' => 'Queried Mod 2']);
            $mod2Version = createSyncedModVersion($mod2, '2.0.0', $this->sptVersion);

            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);
            $sharedDepVersion = createSyncedModVersion($sharedDep, '1.0.0', $this->sptVersion);

            $middleDep = Mod::factory()->create(['name' => 'Middle Dependency']);
            $middleDepVersion = createSyncedModVersion($middleDep, '1.0.0', $this->sptVersion);

            createResolvedDependency($mod1Version, $sharedDep, '^1.0.0', $sharedDepVersion);
            createResolvedDependency($mod2Version, $middleDep, '*', $middleDepVersion);
            createResolvedDependency($middleDepVersion, $sharedDep, '^1.0.0', $sharedDepVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0,%d:2.0.0&spt_version=3.9.0', $mod1->id, $mod2->id));

            $response->assertSuccessful();

            $data = $response->json('data');
            $topLevelEntry = $data[sprintf('%d:1.0.0', $mod1->id)][0];
            $nestedEntry = $data[sprintf('%d:2.0.0', $mod2->id)][0]['dependencies'][0];

            expect($topLevelEntry['name'])->toBe('Shared Dependency')
                ->and($nestedEntry['name'])->toBe('Shared Dependency')
                ->and($topLevelEntry['latest_compatible_version']['id'])
                ->toBe($nestedEntry['latest_compatible_version']['id']);
        });
    });

    describe('edge cases', function (): void {
        it('handles whitespace in comma-separated mods parameter', function (): void {
            $mod = Mod::factory()->create();
            createSyncedModVersion($mod, '1.0.0', $this->sptVersion);

            $url = '/api/v0/mods/dependencies?spt_version=3.9.0&mods='.urlencode(sprintf(' %d:1.0.0 , %d:1.0.0 ', $mod->id, $mod->id));
            $response = $this->getJson($url);

            $response->assertSuccessful();

            expect($response->json('data'))->toBe([sprintf('%d:1.0.0', $mod->id) => []]);
        });

        it('handles duplicate mods in parameter', function (): void {
            $mod = Mod::factory()->create();
            createSyncedModVersion($mod, '1.0.0', $this->sptVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0,%d:1.0.0,%d:1.0.0&spt_version=3.9.0', $mod->id, $mod->id, $mod->id));

            $response->assertSuccessful();

            expect($response->json('data'))->toBe([sprintf('%d:1.0.0', $mod->id) => []]);
        });

        it('prevents circular dependency loops', function (): void {
            $modA = Mod::factory()->create(['name' => 'Mod A']);
            $modAVersion = createSyncedModVersion($modA, '1.0.0', $this->sptVersion);

            $modB = Mod::factory()->create(['name' => 'Mod B']);
            $modBVersion = createSyncedModVersion($modB, '1.0.0', $this->sptVersion);

            createResolvedDependency($modAVersion, $modB, '*', $modBVersion);
            createResolvedDependency($modBVersion, $modA, '*', $modAVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $modA->id));

            $response->assertSuccessful();

            $group = $response->json('data')[sprintf('%d:1.0.0', $modA->id)];

            expect($group)->toHaveCount(1)
                ->and($group[0]['name'])->toBe('Mod B')
                ->and($group[0]['dependencies'])->toHaveCount(1)
                ->and($group[0]['dependencies'][0]['name'])->toBe('Mod A')
                ->and($group[0]['dependencies'][0]['dependencies'])->toBe([]);
        });

        it('collapses multiple constraints on the same mod into one node', function (): void {
            $mainMod = Mod::factory()->create();
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $dependencyVersion = createSyncedModVersion($dependencyMod, '1.5.0', $this->sptVersion);

            createResolvedDependency($mainModVersion, $dependencyMod, '^1.0.0', $dependencyVersion);
            createResolvedDependency($mainModVersion, $dependencyMod, '^1.5.0', $dependencyVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $mainMod->id));

            $response->assertSuccessful();

            $group = $response->json('data')[sprintf('%d:1.0.0', $mainMod->id)];

            expect($group)->toHaveCount(1)
                ->and($group[0]['name'])->toBe('Dependency Mod')
                ->and($group[0]['latest_compatible_version']['version'])->toBe('1.5.0');
        });
    });

    describe('visibility tests', function (): void {
        it('returns an empty object when queried mod version belongs to unpublished mod', function (): void {
            $unpublishedMod = Mod::factory()->create([
                'published_at' => null,
            ]);
            createSyncedModVersion($unpublishedMod, '1.0.0', $this->sptVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $unpublishedMod->id));

            $response->assertSuccessful();

            expect($response->content())->toContain('"data":{}');
        });

        it('returns an empty object when queried mod version belongs to disabled mod', function (): void {
            $disabledMod = Mod::factory()->create([
                'disabled' => true,
            ]);
            createSyncedModVersion($disabledMod, '1.0.0', $this->sptVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $disabledMod->id));

            $response->assertSuccessful();

            expect($response->content())->toContain('"data":{}');
        });

        it('returns an empty object when queried mod version belongs to mod published in the future', function (): void {
            $futureMod = Mod::factory()->create([
                'published_at' => now()->addDay(),
            ]);
            createSyncedModVersion($futureMod, '1.0.0', $this->sptVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $futureMod->id));

            $response->assertSuccessful();

            expect($response->content())->toContain('"data":{}');
        });

        it('excludes unpublished dependency mods from tree', function (): void {
            $mainMod = Mod::factory()->create();
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $publishedDep = Mod::factory()->create(['name' => 'Published Dependency']);
            $publishedDepVersion = createSyncedModVersion($publishedDep, '1.0.0', $this->sptVersion);

            $unpublishedDep = Mod::factory()->create([
                'name' => 'Unpublished Dependency',
                'published_at' => null,
            ]);
            createSyncedModVersion($unpublishedDep, '1.0.0', $this->sptVersion);

            createResolvedDependency($mainModVersion, $publishedDep, '*', $publishedDepVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $mainMod->id));

            $response->assertSuccessful();

            $group = $response->json('data')[sprintf('%d:1.0.0', $mainMod->id)];

            expect($group)->toHaveCount(1)
                ->and($group[0]['name'])->toBe('Published Dependency');
        });

        it('excludes disabled dependency mods from tree', function (): void {
            $mainMod = Mod::factory()->create();
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $publishedDep = Mod::factory()->create(['name' => 'Published Dependency']);
            $publishedDepVersion = createSyncedModVersion($publishedDep, '1.0.0', $this->sptVersion);

            $disabledDep = Mod::factory()->create([
                'name' => 'Disabled Dependency',
                'disabled' => true,
            ]);
            $disabledDepVersion = createSyncedModVersion($disabledDep, '1.0.0', $this->sptVersion);

            createResolvedDependency($mainModVersion, $publishedDep, '*', $publishedDepVersion);
            createResolvedDependency($mainModVersion, $disabledDep, '*', $disabledDepVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $mainMod->id));

            $response->assertSuccessful();

            $group = $response->json('data')[sprintf('%d:1.0.0', $mainMod->id)];

            expect($group)->toHaveCount(1)
                ->and($group[0]['name'])->toBe('Published Dependency');
        });

        it('excludes disabled dependency versions from tree', function (): void {
            $mainMod = Mod::factory()->create();
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $publishedVersion = createSyncedModVersion($dependencyMod, '1.0.0', $this->sptVersion);

            $disabledVersion = ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => true,
            ]);
            $disabledVersion->sptVersions()->sync([$this->sptVersion->id]);

            createResolvedDependency($mainModVersion, $dependencyMod, '*', $publishedVersion);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $mainMod->id));

            $response->assertSuccessful();

            $group = $response->json('data')[sprintf('%d:1.0.0', $mainMod->id)];

            expect($group[0]['latest_compatible_version']['version'])->toBe('1.0.0');
        });

        it('excludes entire dependency chain when middle mod is disabled', function (): void {
            $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
            $mainModVersion = createSyncedModVersion($mainMod, '1.0.0', $this->sptVersion);

            $level1Mod = Mod::factory()->create([
                'name' => 'Level 1 Mod',
                'disabled' => true,
            ]);
            $level1Version = createSyncedModVersion($level1Mod, '1.0.0', $this->sptVersion);

            $level2Mod = Mod::factory()->create(['name' => 'Level 2 Mod']);
            $level2Version = createSyncedModVersion($level2Mod, '1.0.0', $this->sptVersion);

            createResolvedDependency($mainModVersion, $level1Mod, '*', $level1Version);
            createResolvedDependency($level1Version, $level2Mod, '*', $level2Version);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies?mods=%d:1.0.0&spt_version=3.9.0', $mainMod->id));

            $response->assertSuccessful();

            expect($response->json('data'))->toBe([sprintf('%d:1.0.0', $mainMod->id) => []]);
        });
    });
});
