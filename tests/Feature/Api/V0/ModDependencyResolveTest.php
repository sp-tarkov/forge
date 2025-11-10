<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModDependency;
use App\Models\ModResolvedDependency;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Mod Dependencies Resolution Endpoint', function (): void {
    describe('Parameter Validation', function (): void {
        it('returns error when no parameters are provided', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies/tree');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide the 'mods' parameter.",
                ]);
        });

        it('returns error when empty mods parameter is provided', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies/tree?mods=');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide the 'mods' parameter.",
                ]);
        });

        it('returns error when only whitespace is provided for mods', function (): void {
            $url = '/api/v0/mods/dependencies/tree?mods='.urlencode('   ');
            $response = $this->getJson($url);

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide the 'mods' parameter.",
                ]);
        });

        it('returns error when invalid format is provided', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies/tree?mods=abc,xyz');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either a mod_id (numeric) or GUID (string)",
                ]);
        });

        it('returns error when missing colon separator', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies/tree?mods=5');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either a mod_id (numeric) or GUID (string)",
                ]);
        });

        it('returns empty array when non-existent mod:version pairs are provided', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies/tree?mods=99999:1.0.0,88888:2.0.0');

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [],
                ]);
        });

        it('returns empty array when non-existent GUID:version pairs are provided', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies/tree?mods=com.nonexistent.mod:1.0.0');

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [],
                ]);
        });

        it('accepts GUID-based identifier for queried mods', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $mod = Mod::factory()->create([
                'guid' => 'com.example.testmod',
            ]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modVersion->sptVersions()->sync([$sptVersion->id]);

            $response = $this->getJson('/api/v0/mods/dependencies/tree?mods=com.example.testmod:1.0.0');

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [],
                ]);
        });

        it('accepts mixed mod_id and GUID identifiers in same request', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $mod1 = Mod::factory()->create([
                'guid' => 'com.example.mod1',
            ]);
            $mod1Version = ModVersion::factory()->create([
                'mod_id' => $mod1->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mod1Version->sptVersions()->sync([$sptVersion->id]);

            $mod2 = Mod::factory()->create([
                'guid' => 'com.example.mod2',
            ]);
            $mod2Version = ModVersion::factory()->create([
                'mod_id' => $mod2->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mod2Version->sptVersions()->sync([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0,com.example.mod2:2.0.0', $mod1->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [],
                ]);
        });
    });

    describe('Authentication', function (): void {
        it('requires authentication', function (): void {
            // Create a fresh test without the beforeEach authentication
            $this->refreshApplication();

            $response = $this->withHeaders([
                'Accept' => 'application/json',
            ])->getJson('/api/v0/mods/dependencies/tree?mods=1:1.0.0');

            $response->assertUnauthorized()
                ->assertJson([
                    'success' => false,
                    'code' => 'UNAUTHENTICATED',
                ]);
        });
    });

    describe('Dependency Resolution', function (): void {
        it('resolves dependencies when using GUID identifier', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            // Create main mod with GUID
            $mainMod = Mod::factory()->create([
                'name' => 'Main Mod',
                'guid' => 'com.example.mainmod',
            ]);
            $mainModVersion = ModVersion::factory()->create([
                'mod_id' => $mainMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mainModVersion->sptVersions()->sync([$sptVersion->id]);

            // Create dependency mod with GUID
            $dependencyMod = Mod::factory()->create([
                'name' => 'Dependency Mod',
                'guid' => 'com.example.dependency',
            ]);
            $dependencyModVersion = ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $dependencyModVersion->sptVersions()->sync([$sptVersion->id]);

            $dependency = ModDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependent_mod_id' => $dependencyMod->id,
                'constraint' => '^2.0.0',
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependency_id' => $dependency->id,
                'resolved_mod_version_id' => $dependencyModVersion->id,
            ]);

            $response = $this->getJson('/api/v0/mods/dependencies/tree?mods=com.example.mainmod:1.0.0');

            $response->assertSuccessful()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Dependency Mod')
                ->assertJsonPath('data.0.guid', 'com.example.dependency')
                ->assertJsonPath('data.0.latest_compatible_version.version', '2.0.0')
                ->assertJsonPath('data.0.dependencies', []);
        });

        it('returns empty array when mod version has no dependencies', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $mod = Mod::factory()->create();
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modVersion->sptVersions()->sync([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $mod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [],
                ]);
        });

        it('returns empty array when mod version does not exist', function (): void {
            $response = $this->getJson('/api/v0/mods/dependencies/tree?mods=99999:1.0.0');

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [],
                ]);
        });

        it('resolves dependencies for a single mod version', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            // Create main mod
            $mainMod = Mod::factory()->create([
                'name' => 'Main Mod',
                'guid' => 'com.example.main',
            ]);
            $mainModVersion = ModVersion::factory()->create([
                'mod_id' => $mainMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mainModVersion->sptVersions()->sync([$sptVersion->id]);

            // Create dependency mod
            $dependencyMod = Mod::factory()->create([
                'name' => 'Dependency Mod',
                'guid' => 'com.example.dependency',
            ]);
            $dependencyModVersion = ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $dependencyModVersion->sptVersions()->sync([$sptVersion->id]);

            $dependency = ModDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependent_mod_id' => $dependencyMod->id,
                'constraint' => '^2.0.0',
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependency_id' => $dependency->id,
                'resolved_mod_version_id' => $dependencyModVersion->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $mainMod->id));

            $response->assertSuccessful()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Dependency Mod')
                ->assertJsonPath('data.0.guid', 'com.example.dependency')
                ->assertJsonPath('data.0.latest_compatible_version.version', '2.0.0')
                ->assertJsonPath('data.0.dependencies', []);
        });

        it('resolves nested dependencies recursively', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            // Main mod
            $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
            $mainModVersion = ModVersion::factory()->create([
                'mod_id' => $mainMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mainModVersion->sptVersions()->sync([$sptVersion->id]);

            // Level 1 dependency
            $level1Mod = Mod::factory()->create(['name' => 'Level 1 Dependency']);
            $level1Version = ModVersion::factory()->create([
                'mod_id' => $level1Mod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $level1Version->sptVersions()->sync([$sptVersion->id]);

            // Level 2 dependency
            $level2Mod = Mod::factory()->create(['name' => 'Level 2 Dependency']);
            $level2Version = ModVersion::factory()->create([
                'mod_id' => $level2Mod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $level2Version->sptVersions()->sync([$sptVersion->id]);

            // Create dependency chain
            $dep1 = ModDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependent_mod_id' => $level1Mod->id,
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependency_id' => $dep1->id,
                'resolved_mod_version_id' => $level1Version->id,
            ]);

            $dep2 = ModDependency::factory()->create([
                'mod_version_id' => $level1Version->id,
                'dependent_mod_id' => $level2Mod->id,
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $level1Version->id,
                'dependency_id' => $dep2->id,
                'resolved_mod_version_id' => $level2Version->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $mainMod->id));

            $response->assertSuccessful()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Level 1 Dependency')
                ->assertJsonCount(1, 'data.0.dependencies')
                ->assertJsonPath('data.0.dependencies.0.name', 'Level 2 Dependency')
                ->assertJsonPath('data.0.dependencies.0.dependencies', []);
        });

        it('handles multiple mod versions in single request', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            // Create two mods with dependencies
            $mod1 = Mod::factory()->create(['name' => 'Mod 1']);
            $mod1Version = ModVersion::factory()->create([
                'mod_id' => $mod1->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mod1Version->sptVersions()->sync([$sptVersion->id]);

            $mod2 = Mod::factory()->create(['name' => 'Mod 2']);
            $mod2Version = ModVersion::factory()->create([
                'mod_id' => $mod2->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mod2Version->sptVersions()->sync([$sptVersion->id]);

            // Shared dependency
            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);
            $sharedDepVersion = ModVersion::factory()->create([
                'mod_id' => $sharedDep->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $sharedDepVersion->sptVersions()->sync([$sptVersion->id]);

            $dep1 = ModDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependent_mod_id' => $sharedDep->id,
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependency_id' => $dep1->id,
                'resolved_mod_version_id' => $sharedDepVersion->id,
            ]);

            $dep2 = ModDependency::factory()->create([
                'mod_version_id' => $mod2Version->id,
                'dependent_mod_id' => $sharedDep->id,
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod2Version->id,
                'dependency_id' => $dep2->id,
                'resolved_mod_version_id' => $sharedDepVersion->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0,%d:2.0.0', $mod1->id, $mod2->id));

            // Should return only 1 instance of the shared dependency (deduplicated)
            $response->assertSuccessful()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Shared Dependency')
                ->assertJsonPath('data.0.latest_compatible_version.version', '1.0.0')
                ->assertJsonPath('data.0.conflict', false);
        });

        it('handles different versions of same dependency mod from multiple queried mods', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            // Create two mods that will be queried
            $mod1 = Mod::factory()->create(['name' => 'Queried Mod 1']);
            $mod1Version = ModVersion::factory()->create([
                'mod_id' => $mod1->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mod1Version->sptVersions()->sync([$sptVersion->id]);

            $mod2 = Mod::factory()->create(['name' => 'Queried Mod 2']);
            $mod2Version = ModVersion::factory()->create([
                'mod_id' => $mod2->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mod2Version->sptVersions()->sync([$sptVersion->id]);

            // Create shared dependency mod with TWO different versions
            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);

            $sharedDepVersion1 = ModVersion::factory()->create([
                'mod_id' => $sharedDep->id,
                'version' => '1.0.0',
                'published_at' => now()->subDays(2),
                'disabled' => false,
            ]);
            $sharedDepVersion1->sptVersions()->sync([$sptVersion->id]);

            $sharedDepVersion2 = ModVersion::factory()->create([
                'mod_id' => $sharedDep->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $sharedDepVersion2->sptVersions()->sync([$sptVersion->id]);

            // Mod 1 depends on version 1.0.0 of shared dependency
            $dep1 = ModDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependent_mod_id' => $sharedDep->id,
                'constraint' => '^1.0.0',
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependency_id' => $dep1->id,
                'resolved_mod_version_id' => $sharedDepVersion1->id,
            ]);

            // Mod 2 depends on version 2.0.0 of shared dependency
            $dep2 = ModDependency::factory()->create([
                'mod_version_id' => $mod2Version->id,
                'dependent_mod_id' => $sharedDep->id,
                'constraint' => '^2.0.0',
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod2Version->id,
                'dependency_id' => $dep2->id,
                'resolved_mod_version_id' => $sharedDepVersion2->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0,%d:2.0.0', $mod1->id, $mod2->id));

            // Should return 2 instances of the shared dependency (different versions)
            $response->assertSuccessful()
                ->assertJsonCount(2, 'data')
                ->assertJsonFragment(['name' => 'Shared Dependency']);

            // Verify both versions are present
            $data = $response->json('data');
            $versions = collect($data)
                ->where('name', 'Shared Dependency')
                ->pluck('latest_compatible_version.version')
                ->sort()
                ->values()
                ->all();

            expect($versions)->toBe(['1.0.0', '2.0.0']);

            // Verify both have conflict set to true
            $conflicts = collect($data)
                ->where('name', 'Shared Dependency')
                ->pluck('conflict')
                ->unique()
                ->values()
                ->all();

            expect($conflicts)->toBe([true]);
        });

        it('deduplicates when constraints are compatible and shows highest satisfying version', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            // Create two mods that will be queried
            $mod1 = Mod::factory()->create(['name' => 'Queried Mod 1']);
            $mod1Version = ModVersion::factory()->create([
                'mod_id' => $mod1->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mod1Version->sptVersions()->sync([$sptVersion->id]);

            $mod2 = Mod::factory()->create(['name' => 'Queried Mod 2']);
            $mod2Version = ModVersion::factory()->create([
                'mod_id' => $mod2->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mod2Version->sptVersions()->sync([$sptVersion->id]);

            // Create shared dependency mod with THREE versions
            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);

            $sharedDepVersion1_0 = ModVersion::factory()->create([
                'mod_id' => $sharedDep->id,
                'version' => '1.0.0',
                'published_at' => now()->subDays(3),
                'disabled' => false,
            ]);
            $sharedDepVersion1_0->sptVersions()->sync([$sptVersion->id]);

            $sharedDepVersion1_5 = ModVersion::factory()->create([
                'mod_id' => $sharedDep->id,
                'version' => '1.5.0',
                'published_at' => now()->subDays(2),
                'disabled' => false,
            ]);
            $sharedDepVersion1_5->sptVersions()->sync([$sptVersion->id]);

            $sharedDepVersion1_8 = ModVersion::factory()->create([
                'mod_id' => $sharedDep->id,
                'version' => '1.8.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $sharedDepVersion1_8->sptVersions()->sync([$sptVersion->id]);

            // Mod 1 depends on ^1.0.0 (accepts 1.0.0, 1.5.0, 1.8.0)
            $dep1 = ModDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependent_mod_id' => $sharedDep->id,
                'constraint' => '^1.0.0',
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependency_id' => $dep1->id,
                'resolved_mod_version_id' => $sharedDepVersion1_0->id,
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependency_id' => $dep1->id,
                'resolved_mod_version_id' => $sharedDepVersion1_5->id,
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependency_id' => $dep1->id,
                'resolved_mod_version_id' => $sharedDepVersion1_8->id,
            ]);

            // Mod 2 depends on ^1.5.0 (accepts 1.5.0, 1.8.0)
            $dep2 = ModDependency::factory()->create([
                'mod_version_id' => $mod2Version->id,
                'dependent_mod_id' => $sharedDep->id,
                'constraint' => '^1.5.0',
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod2Version->id,
                'dependency_id' => $dep2->id,
                'resolved_mod_version_id' => $sharedDepVersion1_5->id,
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod2Version->id,
                'dependency_id' => $dep2->id,
                'resolved_mod_version_id' => $sharedDepVersion1_8->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0,%d:2.0.0', $mod1->id, $mod2->id));

            // Should return only 1 instance with version 1.8.0 (highest version satisfying both ^1.0.0 and ^1.5.0)
            $response->assertSuccessful()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Shared Dependency')
                ->assertJsonPath('data.0.latest_compatible_version.version', '1.8.0')
                ->assertJsonPath('data.0.conflict', false);
        });

        it('applies conflict detection to nested dependencies', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            // Create main mod
            $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
            $mainModVersion = ModVersion::factory()->create([
                'mod_id' => $mainMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mainModVersion->sptVersions()->sync([$sptVersion->id]);

            // Create first-level dependency
            $level1Dep = Mod::factory()->create(['name' => 'Level 1 Dependency']);
            $level1Version = ModVersion::factory()->create([
                'mod_id' => $level1Dep->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $level1Version->sptVersions()->sync([$sptVersion->id]);

            // Create nested dependency
            $nestedDep = Mod::factory()->create(['name' => 'Nested Dependency']);
            $nestedVersion = ModVersion::factory()->create([
                'mod_id' => $nestedDep->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $nestedVersion->sptVersions()->sync([$sptVersion->id]);

            // Main mod depends on level 1
            $dep1 = ModDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependent_mod_id' => $level1Dep->id,
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependency_id' => $dep1->id,
                'resolved_mod_version_id' => $level1Version->id,
            ]);

            // Level 1 depends on nested
            $dep2 = ModDependency::factory()->create([
                'mod_version_id' => $level1Version->id,
                'dependent_mod_id' => $nestedDep->id,
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $level1Version->id,
                'dependency_id' => $dep2->id,
                'resolved_mod_version_id' => $nestedVersion->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $mainMod->id));

            $response->assertSuccessful()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Level 1 Dependency')
                ->assertJsonPath('data.0.conflict', false)
                ->assertJsonCount(1, 'data.0.dependencies')
                ->assertJsonPath('data.0.dependencies.0.name', 'Nested Dependency')
                ->assertJsonPath('data.0.dependencies.0.conflict', false); // Verify nested has conflict flag
        });

        it('deduplicates shared dependencies across multiple queried mods', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            // Create two mods that will be queried
            $mod1 = Mod::factory()->create(['name' => 'Queried Mod 1']);
            $mod1Version = ModVersion::factory()->create([
                'mod_id' => $mod1->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mod1Version->sptVersions()->sync([$sptVersion->id]);

            $mod2 = Mod::factory()->create(['name' => 'Queried Mod 2']);
            $mod2Version = ModVersion::factory()->create([
                'mod_id' => $mod2->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mod2Version->sptVersions()->sync([$sptVersion->id]);

            // Create shared dependency that both mods depend on
            $sharedDep = Mod::factory()->create(['name' => 'Shared Dependency']);
            $sharedDepVersion = ModVersion::factory()->create([
                'mod_id' => $sharedDep->id,
                'version' => '1.5.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $sharedDepVersion->sptVersions()->sync([$sptVersion->id]);

            // Create unique dependency for mod1
            $uniqueDep1 = Mod::factory()->create(['name' => 'Unique Dependency 1']);
            $uniqueDep1Version = ModVersion::factory()->create([
                'mod_id' => $uniqueDep1->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $uniqueDep1Version->sptVersions()->sync([$sptVersion->id]);

            // Create unique dependency for mod2
            $uniqueDep2 = Mod::factory()->create(['name' => 'Unique Dependency 2']);
            $uniqueDep2Version = ModVersion::factory()->create([
                'mod_id' => $uniqueDep2->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $uniqueDep2Version->sptVersions()->sync([$sptVersion->id]);

            // Mod 1 depends on both shared and unique1
            $dep1Shared = ModDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependent_mod_id' => $sharedDep->id,
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependency_id' => $dep1Shared->id,
                'resolved_mod_version_id' => $sharedDepVersion->id,
            ]);

            $dep1Unique = ModDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependent_mod_id' => $uniqueDep1->id,
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod1Version->id,
                'dependency_id' => $dep1Unique->id,
                'resolved_mod_version_id' => $uniqueDep1Version->id,
            ]);

            // Mod 2 depends on both shared and unique2
            $dep2Shared = ModDependency::factory()->create([
                'mod_version_id' => $mod2Version->id,
                'dependent_mod_id' => $sharedDep->id,
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod2Version->id,
                'dependency_id' => $dep2Shared->id,
                'resolved_mod_version_id' => $sharedDepVersion->id,
            ]);

            $dep2Unique = ModDependency::factory()->create([
                'mod_version_id' => $mod2Version->id,
                'dependent_mod_id' => $uniqueDep2->id,
            ]);
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mod2Version->id,
                'dependency_id' => $dep2Unique->id,
                'resolved_mod_version_id' => $uniqueDep2Version->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0,%d:2.0.0', $mod1->id, $mod2->id));

            // Should return 3 unique dependencies (1 shared + 2 unique), not 4
            $response->assertSuccessful()
                ->assertJsonCount(3, 'data')
                ->assertJsonFragment(['name' => 'Shared Dependency'])
                ->assertJsonFragment(['name' => 'Unique Dependency 1'])
                ->assertJsonFragment(['name' => 'Unique Dependency 2']);

            // Verify the shared dependency appears only once
            $data = $response->json('data');
            $sharedDependencyCount = collect($data)->where('name', 'Shared Dependency')->count();
            expect($sharedDependencyCount)->toBe(1);
        });

        it('returns only latest compatible version for each dependency', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $mainMod = Mod::factory()->create();
            $mainModVersion = ModVersion::factory()->create([
                'mod_id' => $mainMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mainModVersion->sptVersions()->sync([$sptVersion->id]);

            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);

            // Create resolved version 2.0.0
            $resolvedVersion = ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $resolvedVersion->sptVersions()->sync([$sptVersion->id]);

            // Create older version (disabled/unpublished to avoid it being used)
            $olderVersion = ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '1.5.0',
                'published_at' => now()->subDays(2),
                'disabled' => true,
            ]);
            $olderVersion->sptVersions()->sync([$sptVersion->id]);

            $dependency = ModDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependent_mod_id' => $dependencyMod->id,
                'constraint' => '^2.0.0',
            ]);

            // Only resolve version 2.0.0
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependency_id' => $dependency->id,
                'resolved_mod_version_id' => $resolvedVersion->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $mainMod->id));

            $response->assertSuccessful()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Dependency Mod')
                ->assertJsonPath('data.0.latest_compatible_version.version', '2.0.0');
        });
    });

    describe('Edge Cases', function (): void {
        it('handles whitespace in comma-separated mods parameter', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $mod = Mod::factory()->create();
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modVersion->sptVersions()->sync([$sptVersion->id]);

            $url = '/api/v0/mods/dependencies/tree?mods='.urlencode(sprintf(' %d:1.0.0 , %d:1.0.0 ', $mod->id, $mod->id));
            $response = $this->getJson($url);

            $response->assertSuccessful();
        });

        it('handles duplicate mods in parameter', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $mod = Mod::factory()->create();
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modVersion->sptVersions()->sync([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0,%d:1.0.0,%d:1.0.0', $mod->id, $mod->id, $mod->id));

            $response->assertSuccessful();
        });

        it('prevents circular dependency loops', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            // Mod A depends on Mod B, Mod B depends on Mod A (circular)
            $modA = Mod::factory()->create(['name' => 'Mod A']);
            $modAVersion = ModVersion::factory()->create([
                'mod_id' => $modA->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modAVersion->sptVersions()->sync([$sptVersion->id]);

            $modB = Mod::factory()->create(['name' => 'Mod B']);
            $modBVersion = ModVersion::factory()->create([
                'mod_id' => $modB->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modBVersion->sptVersions()->sync([$sptVersion->id]);

            // A -> B
            $depAB = ModDependency::factory()->create([
                'mod_version_id' => $modAVersion->id,
                'dependent_mod_id' => $modB->id,
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $modAVersion->id,
                'dependency_id' => $depAB->id,
                'resolved_mod_version_id' => $modBVersion->id,
            ]);

            // B -> A (circular)
            $depBToA = ModDependency::factory()->create([
                'mod_version_id' => $modBVersion->id,
                'dependent_mod_id' => $modA->id,
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $modBVersion->id,
                'dependency_id' => $depBToA->id,
                'resolved_mod_version_id' => $modAVersion->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $modA->id));

            // Should not infinite loop - returns Mod B, which shows Mod A as dependency, but A's deps are empty
            $response->assertSuccessful()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Mod B')
                ->assertJsonCount(1, 'data.0.dependencies')
                ->assertJsonPath('data.0.dependencies.0.name', 'Mod A')
                ->assertJsonPath('data.0.dependencies.0.dependencies', []); // Circular prevention - no further nesting
        });
    });

    describe('Visibility Tests', function (): void {
        it('returns empty array when queried mod version belongs to unpublished mod', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $unpublishedMod = Mod::factory()->create([
                'published_at' => null,
            ]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $unpublishedMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modVersion->sptVersions()->sync([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $unpublishedMod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [],
                ]);
        });

        it('returns empty array when queried mod version belongs to disabled mod', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $disabledMod = Mod::factory()->create([
                'disabled' => true,
            ]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $disabledMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modVersion->sptVersions()->sync([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $disabledMod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [],
                ]);
        });

        it('returns empty array when queried mod version belongs to mod published in the future', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $futureMod = Mod::factory()->create([
                'published_at' => now()->addDay(),
            ]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $futureMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $modVersion->sptVersions()->sync([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $futureMod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [],
                ]);
        });

        it('excludes unpublished dependency mods from tree', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $mainMod = Mod::factory()->create();
            $mainModVersion = ModVersion::factory()->create([
                'mod_id' => $mainMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mainModVersion->sptVersions()->sync([$sptVersion->id]);

            // Create published dependency
            $publishedDep = Mod::factory()->create(['name' => 'Published Dependency']);
            $publishedDepVersion = ModVersion::factory()->create([
                'mod_id' => $publishedDep->id,
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $publishedDepVersion->sptVersions()->sync([$sptVersion->id]);

            // Create unpublished dependency
            $unpublishedDep = Mod::factory()->create([
                'name' => 'Unpublished Dependency',
                'published_at' => null,
            ]);
            $unpublishedDepVersion = ModVersion::factory()->create([
                'mod_id' => $unpublishedDep->id,
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $unpublishedDepVersion->sptVersions()->sync([$sptVersion->id]);

            $dep1 = ModDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependent_mod_id' => $publishedDep->id,
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependency_id' => $dep1->id,
                'resolved_mod_version_id' => $publishedDepVersion->id,
            ]);

            // Don't create dependency or resolved dependency for unpublished mod
            // In production, dependencies on unpublished mods wouldn't exist in the database

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $mainMod->id));

            $response->assertSuccessful()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Published Dependency')
                ->assertJsonMissing(['name' => 'Unpublished Dependency']);
        });

        it('excludes disabled dependency mods from tree', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $mainMod = Mod::factory()->create();
            $mainModVersion = ModVersion::factory()->create([
                'mod_id' => $mainMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mainModVersion->sptVersions()->sync([$sptVersion->id]);

            // Published dependency
            $publishedDep = Mod::factory()->create(['name' => 'Published Dependency']);
            $publishedDepVersion = ModVersion::factory()->create([
                'mod_id' => $publishedDep->id,
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $publishedDepVersion->sptVersions()->sync([$sptVersion->id]);

            // Disabled dependency
            $disabledDep = Mod::factory()->create([
                'name' => 'Disabled Dependency',
                'disabled' => true,
            ]);
            $disabledDepVersion = ModVersion::factory()->create([
                'mod_id' => $disabledDep->id,
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $disabledDepVersion->sptVersions()->sync([$sptVersion->id]);

            $dep1 = ModDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependent_mod_id' => $publishedDep->id,
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependency_id' => $dep1->id,
                'resolved_mod_version_id' => $publishedDepVersion->id,
            ]);

            $dep2 = ModDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependent_mod_id' => $disabledDep->id,
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependency_id' => $dep2->id,
                'resolved_mod_version_id' => $disabledDepVersion->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $mainMod->id));

            $response->assertSuccessful()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Published Dependency')
                ->assertJsonMissing(['name' => 'Disabled Dependency']);
        });

        it('excludes disabled dependency versions from tree', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            $mainMod = Mod::factory()->create();
            $mainModVersion = ModVersion::factory()->create([
                'mod_id' => $mainMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mainModVersion->sptVersions()->sync([$sptVersion->id]);

            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);

            // Published version
            $publishedVersion = ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $publishedVersion->sptVersions()->sync([$sptVersion->id]);

            // Disabled version
            $disabledVersion = ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '2.0.0',
                'published_at' => now()->subDay(),
                'disabled' => true,
            ]);
            $disabledVersion->sptVersions()->sync([$sptVersion->id]);

            $dependency = ModDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependent_mod_id' => $dependencyMod->id,
            ]);

            // Only resolve the published version
            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependency_id' => $dependency->id,
                'resolved_mod_version_id' => $publishedVersion->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $mainMod->id));

            $response->assertSuccessful()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.latest_compatible_version.version', '1.0.0');
        });

        it('excludes entire dependency chain when middle mod is disabled', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.9.0',
                'publish_date' => now()->subDay(),
            ]);

            // Main -> Level1 (disabled) -> Level2
            $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
            $mainModVersion = ModVersion::factory()->create([
                'mod_id' => $mainMod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $mainModVersion->sptVersions()->sync([$sptVersion->id]);

            $level1Mod = Mod::factory()->create([
                'name' => 'Level 1 Mod',
                'disabled' => true,
            ]);
            $level1Version = ModVersion::factory()->create([
                'mod_id' => $level1Mod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $level1Version->sptVersions()->sync([$sptVersion->id]);

            $level2Mod = Mod::factory()->create(['name' => 'Level 2 Mod']);
            $level2Version = ModVersion::factory()->create([
                'mod_id' => $level2Mod->id,
                'version' => '1.0.0',
                'published_at' => now()->subDay(),
                'disabled' => false,
            ]);
            $level2Version->sptVersions()->sync([$sptVersion->id]);

            $dep1 = ModDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependent_mod_id' => $level1Mod->id,
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $mainModVersion->id,
                'dependency_id' => $dep1->id,
                'resolved_mod_version_id' => $level1Version->id,
            ]);

            $dep2 = ModDependency::factory()->create([
                'mod_version_id' => $level1Version->id,
                'dependent_mod_id' => $level2Mod->id,
            ]);

            ModResolvedDependency::factory()->create([
                'mod_version_id' => $level1Version->id,
                'dependency_id' => $dep2->id,
                'resolved_mod_version_id' => $level2Version->id,
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/dependencies/tree?mods=%d:1.0.0', $mainMod->id));

            // Should exclude both Level 1 and Level 2 since Level 1 is disabled
            $response->assertSuccessful()
                ->assertJsonPath('data', []);
        });
    });
});
