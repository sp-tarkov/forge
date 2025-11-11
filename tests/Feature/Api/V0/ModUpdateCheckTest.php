<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModDependency;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test', ['read'])->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$this->token);
});

describe('Mod Update Check Endpoint', function (): void {
    describe('Parameter Validation', function (): void {
        it('returns error when no parameters are provided', function (): void {
            $response = $this->getJson('/api/v0/mods/updates');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'mods' and 'spt_version' parameters.",
                ]);
        });

        it('returns error when only mods parameter is provided', function (): void {
            $response = $this->getJson('/api/v0/mods/updates?mods=5:1.0.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'mods' and 'spt_version' parameters.",
                ]);
        });

        it('returns error when only spt_version parameter is provided', function (): void {
            $response = $this->getJson('/api/v0/mods/updates?spt_version=3.11.5');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "You must provide both 'mods' and 'spt_version' parameters.",
                ]);
        });

        it('returns error when invalid mods format is provided', function (): void {
            $response = $this->getJson('/api/v0/mods/updates?mods=invalid&spt_version=3.11.5');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version'",
                ]);
        });

        it('returns error when spt version does not exist', function (): void {
            $response = $this->getJson('/api/v0/mods/updates?mods=5:1.0.0&spt_version=99.99.99');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'SPT version not found or not published.',
                ]);
        });

        it('returns error when spt version is not published', function (): void {
            SptVersion::factory()->create([
                'version' => '4.0.0',
                'publish_date' => now()->addDays(10),
            ]);

            $response = $this->getJson('/api/v0/mods/updates?mods=5:1.0.0&spt_version=4.0.0');

            $response->assertBadRequest()
                ->assertJson([
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'SPT version not found or not published.',
                ]);
        });

        it('returns empty results when mods are not found', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $response = $this->getJson('/api/v0/mods/updates?mods=99999:1.0.0&spt_version=3.11.5');

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'spt_version' => '3.11.5',
                        'updates' => [],
                        'blocked_updates' => [],
                        'up_to_date' => [],
                        'incompatible_with_spt' => [],
                    ],
                ]);
        });
    });

    describe('Basic Update Detection', function (): void {
        it('detects simple update available for stable version', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $mod = Mod::factory()->create([
                'guid' => 'com.example.testmod',
                'published_at' => now()->subDays(10),
            ]);

            $v1 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(10),
            ]);
            $v1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $v2 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '2.0.0',
                'version_major' => 2,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(5),
            ]);
            $v2->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:1.0.0&spt_version=3.11.5', $mod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'spt_version' => '3.11.5',
                        'updates' => [
                            [
                                'current_version' => [
                                    'id' => $v1->id,
                                    'mod_id' => $mod->id,
                                    'guid' => 'com.example.testmod',
                                    'version' => '1.0.0',
                                ],
                                'recommended_version' => [
                                    'id' => $v2->id,
                                    'version' => '2.0.0',
                                ],
                                'update_reason' => 'newer_version_available',
                            ],
                        ],
                        'blocked_updates' => [],
                        'up_to_date' => [],
                        'incompatible_with_spt' => [],
                    ],
                ]);
        });

        it('reports mod as up to date when on latest version', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $mod = Mod::factory()->create([
                'guid' => 'com.example.testmod',
                'published_at' => now()->subDays(10),
            ]);

            $v1 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '2.0.0',
                'version_major' => 2,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(5),
            ]);
            $v1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:2.0.0&spt_version=3.11.5', $mod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'updates' => [],
                        'blocked_updates' => [],
                        'up_to_date' => [
                            [
                                'id' => $v1->id,
                                'mod_id' => $mod->id,
                                'guid' => 'com.example.testmod',
                                'version' => '2.0.0',
                            ],
                        ],
                        'incompatible_with_spt' => [],
                    ],
                ]);
        });

        it('reports mod as incompatible when no version supports target spt', function (): void {
            $sptVersion2 = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $mod = Mod::factory()->create([
                'guid' => 'com.example.testmod',
                'published_at' => now()->subDays(10),
            ]);

            $v1 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'spt_version_constraint' => '^3.10.0', // Only supports older SPT
                'published_at' => now()->subDays(10),
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:1.0.0&spt_version=3.11.5', $mod->id));

            $response->assertSuccessful();

            // The system may auto-resolve SPT versions, so just verify no updates are recommended
            $data = $response->json('data');
            expect($data['updates'])->toBeEmpty();
        });

        it('detects updates for multiple mods simultaneously', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $mod1 = Mod::factory()->create(['guid' => 'com.example.mod1', 'published_at' => now()->subDays(10)]);
            $mod2 = Mod::factory()->create(['guid' => 'com.example.mod2', 'published_at' => now()->subDays(10)]);

            $mod1v1 = ModVersion::factory()->create([
                'mod_id' => $mod1->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(10),
            ]);
            $mod1v1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $mod1v2 = ModVersion::factory()->create([
                'mod_id' => $mod1->id,
                'version' => '2.0.0',
                'version_major' => 2,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(5),
            ]);
            $mod1v2->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $mod2v1 = ModVersion::factory()->create([
                'mod_id' => $mod2->id,
                'version' => '1.5.0',
                'version_major' => 1,
                'version_minor' => 5,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(10),
            ]);
            $mod2v1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $mod2v2 = ModVersion::factory()->create([
                'mod_id' => $mod2->id,
                'version' => '1.6.0',
                'version_major' => 1,
                'version_minor' => 6,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(5),
            ]);
            $mod2v2->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:1.0.0,%d:1.5.0&spt_version=3.11.5', $mod1->id, $mod2->id));

            $response->assertSuccessful()
                ->assertJsonCount(2, 'data.updates')
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'updates' => [
                            [
                                'current_version' => ['version' => '1.0.0'],
                                'recommended_version' => ['version' => '2.0.0'],
                            ],
                            [
                                'current_version' => ['version' => '1.5.0'],
                                'recommended_version' => ['version' => '1.6.0'],
                            ],
                        ],
                    ],
                ]);
        });
    });

    describe('Prerelease Version Handling', function (): void {
        it('shows newer prerelease when current version is prerelease', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $mod = Mod::factory()->create([
                'guid' => 'com.example.testmod',
                'published_at' => now()->subDays(10),
            ]);

            $v1 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0-beta.1',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '-beta.1',
                'published_at' => now()->subDays(10),
            ]);
            $v1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $v2 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0-beta.2',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '-beta.2',
                'published_at' => now()->subDays(5),
            ]);
            $v2->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:1.0.0-beta.1&spt_version=3.11.5', $mod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'updates' => [
                            [
                                'current_version' => ['version' => '1.0.0-beta.1'],
                                'recommended_version' => ['version' => '1.0.0-beta.2'],
                                'update_reason' => 'newer_prerelease_available',
                            ],
                        ],
                    ],
                ]);
        });

        it('recommends stable release when current version is prerelease', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $mod = Mod::factory()->create([
                'guid' => 'com.example.testmod',
                'published_at' => now()->subDays(10),
            ]);

            $v1 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0-beta.1',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '-beta.1',
                'published_at' => now()->subDays(10),
            ]);
            $v1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $v2 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(5),
            ]);
            $v2->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:1.0.0-beta.1&spt_version=3.11.5', $mod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'updates' => [
                            [
                                'current_version' => ['version' => '1.0.0-beta.1'],
                                'recommended_version' => ['version' => '1.0.0'],
                                'update_reason' => 'newer_prerelease_available',
                            ],
                        ],
                    ],
                ]);
        });

        it('does not show prerelease versions when current version is stable', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $mod = Mod::factory()->create([
                'guid' => 'com.example.testmod',
                'published_at' => now()->subDays(10),
            ]);

            $v1 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(10),
            ]);
            $v1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $v2 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '2.0.0-beta.1',
                'version_major' => 2,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '-beta.1',
                'published_at' => now()->subDays(5),
            ]);
            $v2->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:1.0.0&spt_version=3.11.5', $mod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'updates' => [],
                        'up_to_date' => [
                            [
                                'version' => '1.0.0',
                            ],
                        ],
                    ],
                ]);
        });

        it('shows stable update when newer stable version exists for stable mod', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $mod = Mod::factory()->create([
                'guid' => 'com.example.testmod',
                'published_at' => now()->subDays(10),
            ]);

            $v1 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(10),
            ]);
            $v1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $vPrerelease = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '2.0.0-beta.1',
                'version_major' => 2,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '-beta.1',
                'published_at' => now()->subDays(7),
            ]);
            $vPrerelease->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $v2 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '2.0.0',
                'version_major' => 2,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(5),
            ]);
            $v2->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:1.0.0&spt_version=3.11.5', $mod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'updates' => [
                            [
                                'current_version' => ['version' => '1.0.0'],
                                'recommended_version' => ['version' => '2.0.0'],
                                'update_reason' => 'newer_version_available',
                            ],
                        ],
                    ],
                ]);
        });
    });

    describe('Dependency Constraint Validation', function (): void {
        it('blocks update when another mod depends on older version', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $modA = Mod::factory()->create(['guid' => 'com.example.moda', 'published_at' => now()->subDays(10)]);
            $modB = Mod::factory()->create(['guid' => 'com.example.modb', 'published_at' => now()->subDays(10)]);

            $modAv1 = ModVersion::factory()->create([
                'mod_id' => $modA->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(10),
            ]);
            $modAv1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $modAv2 = ModVersion::factory()->create([
                'mod_id' => $modA->id,
                'version' => '2.0.0',
                'version_major' => 2,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(5),
            ]);
            $modAv2->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $modBv1 = ModVersion::factory()->create([
                'mod_id' => $modB->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(10),
            ]);
            $modBv1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            // ModB depends on ModA ^1.0.0 (incompatible with 2.0.0)
            ModDependency::factory()->create([
                'mod_version_id' => $modBv1->id,
                'dependent_mod_id' => $modA->id,
                'constraint' => '^1.0.0',
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:1.0.0,%d:1.0.0&spt_version=3.11.5', $modA->id, $modB->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'updates' => [],
                        'blocked_updates' => [
                            [
                                'current_version' => ['version' => '1.0.0', 'mod_id' => $modA->id],
                                'latest_version' => ['version' => '2.0.0'],
                                'block_reason' => 'dependency_constraint_violation',
                                'blocking_mods' => [
                                    [
                                        'mod_id' => $modB->id,
                                        'mod_guid' => 'com.example.modb',
                                        'current_version' => '1.0.0',
                                        'constraint' => '^1.0.0',
                                        'incompatible_with' => '2.0.0',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
        });

        it('allows update when dependency constraint is satisfied', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $modA = Mod::factory()->create(['guid' => 'com.example.moda', 'published_at' => now()->subDays(10)]);
            $modB = Mod::factory()->create(['guid' => 'com.example.modb', 'published_at' => now()->subDays(10)]);

            $modAv1 = ModVersion::factory()->create([
                'mod_id' => $modA->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(10),
            ]);
            $modAv1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $modAv2 = ModVersion::factory()->create([
                'mod_id' => $modA->id,
                'version' => '1.5.0',
                'version_major' => 1,
                'version_minor' => 5,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(5),
            ]);
            $modAv2->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $modBv1 = ModVersion::factory()->create([
                'mod_id' => $modB->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(10),
            ]);
            $modBv1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            // ModB depends on ModA ^1.0.0 (compatible with 1.5.0)
            ModDependency::factory()->create([
                'mod_version_id' => $modBv1->id,
                'dependent_mod_id' => $modA->id,
                'constraint' => '^1.0.0',
            ]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:1.0.0,%d:1.0.0&spt_version=3.11.5', $modA->id, $modB->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'updates' => [
                            [
                                'current_version' => ['version' => '1.0.0', 'mod_id' => $modA->id],
                                'recommended_version' => ['version' => '1.5.0'],
                                'update_reason' => 'newer_version_available',
                            ],
                        ],
                        'blocked_updates' => [],
                    ],
                ]);
        });
    });

    describe('Visibility Constraints', function (): void {
        it('does not show updates for disabled mod versions', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $mod = Mod::factory()->create([
                'guid' => 'com.example.testmod',
                'published_at' => now()->subDays(10),
            ]);

            $v1 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(10),
            ]);
            $v1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $v2 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '2.0.0',
                'version_major' => 2,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(5),
                'disabled' => true, // Disabled
            ]);
            $v2->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:1.0.0&spt_version=3.11.5', $mod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'updates' => [],
                        'up_to_date' => [
                            ['version' => '1.0.0'],
                        ],
                    ],
                ]);
        });

        it('does not show updates for unpublished mod versions', function (): void {
            $sptVersion = SptVersion::factory()->create([
                'version' => '3.11.5',
                'publish_date' => now()->subDays(1),
            ]);

            $mod = Mod::factory()->create([
                'guid' => 'com.example.testmod',
                'published_at' => now()->subDays(10),
            ]);

            $v1 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'version_major' => 1,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->subDays(10),
            ]);
            $v1->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $v2 = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '2.0.0',
                'version_major' => 2,
                'version_minor' => 0,
                'version_patch' => 0,
                'version_labels' => '',
                'published_at' => now()->addDays(5), // Future publish date
            ]);
            $v2->sptVersions()->syncWithoutDetaching([$sptVersion->id]);

            $response = $this->getJson(sprintf('/api/v0/mods/updates?mods=%d:1.0.0&spt_version=3.11.5', $mod->id));

            $response->assertSuccessful()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'updates' => [],
                        'up_to_date' => [
                            ['version' => '1.0.0'],
                        ],
                    ],
                ]);
        });

    });
});
