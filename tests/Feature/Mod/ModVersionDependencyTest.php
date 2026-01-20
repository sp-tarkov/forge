<?php

declare(strict_types=1);

use App\Models\Dependency;
use App\Models\DependencyResolved;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Services\DependencyVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('Mod Version Dependencies', function (): void {

    describe('Dependency Resolution', function (): void {
        it('resolves mod version dependencies on create', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $modVersion = ModVersion::factory()->create();

            $dependentMod = Mod::factory()->create();
            $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency
            Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0', // Should resolve to dependentVersion1
            ]);

            // Check that the resolved dependency has been created
            expect(DependencyResolved::query()->where('dependable_id', $modVersion->id)->first())
                ->not()->toBeNull()
                ->resolved_mod_version_id->toBe($dependentVersion1->id);
        });

        it('resolves multiple matching versions', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $modVersion = ModVersion::factory()->create();

            $dependentMod = Mod::factory()->create();
            $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.1.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion3 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency
            Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0', // Should resolve to dependentVersion1 and dependentVersion2
            ]);

            $dependenciesResolved = DependencyResolved::query()->where('dependable_id', $modVersion->id)->get();

            expect($dependenciesResolved->count())->toBe(2)
                ->and($dependenciesResolved->pluck('resolved_mod_version_id'))
                ->toContain($dependentVersion1->id)
                ->toContain($dependentVersion2->id);
        });

        it('does not resolve dependencies when no versions match', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $modVersion = ModVersion::factory()->create();

            $dependentMod = Mod::factory()->create();
            ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);
            ModVersion::factory()->recycle($dependentMod)->create(['version' => '3.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency
            Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0', // No versions match
            ]);

            // Check that no resolved dependencies were created
            expect(DependencyResolved::query()->where('dependable_id', $modVersion->id)->exists())->toBeFalse();
        });

        it('updates resolved dependencies when constraint changes', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $modVersion = ModVersion::factory()->create();

            $dependentMod = Mod::factory()->create();
            $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency with an initial constraint
            $dependency = Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0', // Should resolve to dependentVersion1
            ]);

            $resolvedDependency = DependencyResolved::query()->where('dependable_id', $modVersion->id)->first();
            expect($resolvedDependency->resolved_mod_version_id)->toBe($dependentVersion1->id);

            // Update the constraint
            $dependency->update(['constraint' => '^2.0']); // Should now resolve to dependentVersion2

            $resolvedDependency = DependencyResolved::query()->where('dependable_id', $modVersion->id)->first();
            expect($resolvedDependency->resolved_mod_version_id)->toBe($dependentVersion2->id);
        });

        it('removes resolved dependencies when dependency is removed', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $modVersion = ModVersion::factory()->create();

            $dependentMod = Mod::factory()->create();
            $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency
            $dependency = Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0',
            ]);

            $resolvedDependency = DependencyResolved::query()->where('dependable_id', $modVersion->id)->first();
            expect($resolvedDependency)->not()->toBeNull();

            // Delete the dependency
            $dependency->delete();

            // Check that the resolved dependency is removed
            expect(DependencyResolved::query()->where('dependable_id', $modVersion->id)->exists())->toBeFalse();
        });

        it('handles mod versions with no dependencies gracefully', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $serviceSpy = $this->spy(DependencyVersionService::class);

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            // Check that the service was called and that no resolved dependencies were created.
            $serviceSpy->shouldHaveReceived('resolve');
            expect(DependencyResolved::query()->where('dependable_id', $modVersion->id)->exists())->toBeFalse();
        });

        it('resolves the correct versions with a complex semver constraint', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            $dependentMod = Mod::factory()->create();
            $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.2.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion3 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.5.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion4 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion5 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.5.0', 'spt_version_constraint' => '3.8.0']);

            // Create a complex SemVer constraint
            Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '>1.0 <2.0 || >=2.5.0 <3.0', // Should resolve to dependentVersion2, dependentVersion3, and dependentVersion5
            ]);

            $dependenciesResolved = DependencyResolved::query()->where('dependable_id', $modVersion->id)->pluck('resolved_mod_version_id');

            expect($dependenciesResolved)->toContain($dependentVersion2->id)
                ->toContain($dependentVersion3->id)
                ->toContain($dependentVersion5->id)
                ->not->toContain($dependentVersion1->id)
                ->not->toContain($dependentVersion4->id);
        });

        it('resolves overlapping version constraints from multiple dependencies correctly', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            $dependentMod1 = Mod::factory()->create();
            $dependentVersion1_1 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion1_2 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '1.5.0', 'spt_version_constraint' => '3.8.0']);

            $dependentMod2 = Mod::factory()->create();
            $dependentVersion2_1 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion2_2 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.5.0', 'spt_version_constraint' => '3.8.0']);

            // Create two dependencies with overlapping constraints
            Dependency::factory()->recycle([$modVersion, $dependentMod1])->create([
                'constraint' => '>=1.0 <2.0', // Matches both versions of dependentMod1
            ]);

            Dependency::factory()->recycle([$modVersion, $dependentMod2])->create([
                'constraint' => '>=1.5.0 <2.0.0', // Matches only the second version of dependentMod2
            ]);

            $dependenciesResolved = DependencyResolved::query()->where('dependable_id', $modVersion->id)->get();

            expect($dependenciesResolved->pluck('resolved_mod_version_id'))
                ->toContain($dependentVersion1_1->id)
                ->toContain($dependentVersion1_2->id)
                ->toContain($dependentVersion2_2->id)
                ->not->toContain($dependentVersion2_1->id);
        });

        it('handles the case where a dependent mod has no versions available', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod = Mod::factory()->create();

            // Create a dependency where the dependent mod has no versions.
            Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '>=1.0.0',
            ]);

            // Verify that no versions were resolved.
            expect(DependencyResolved::query()->where('dependable_id', $modVersion->id)->exists())->toBeFalse();
        });

        it('calls DependencyVersionService when a Mod is updated', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

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

        it('calls DependencyVersionService when a ModVersion is updated', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            $serviceSpy = $this->spy(DependencyVersionService::class);

            $modVersion->update(['version' => '1.1.0']);

            $serviceSpy->shouldHaveReceived('resolve');
        });

        it('calls DependencyVersionService when a ModVersion is deleted', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            $serviceSpy = $this->spy(DependencyVersionService::class);

            $modVersion->delete();

            $serviceSpy->shouldHaveReceived('resolve');
        });

        it('calls DependencyVersionService when a ModDependency is updated', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod = Mod::factory()->create();
            $modDependency = Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0',
            ]);

            $serviceSpy = $this->spy(DependencyVersionService::class);

            $modDependency->update(['constraint' => '^2.0']);

            $serviceSpy->shouldHaveReceived('resolve');
        });

        it('calls DependencyVersionService when a ModDependency is deleted', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod = Mod::factory()->create();
            $modDependency = Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0',
            ]);

            $serviceSpy = $this->spy(DependencyVersionService::class);

            $modDependency->delete();

            $serviceSpy->shouldHaveReceived('resolve');
        });

        it('does not return duplicate entries from latestDependenciesResolved when duplicate records exist', function (): void {
            $this->withoutDefer();
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            // Create a mod version WITHOUT triggering the observer
            $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
            $mainModVersion = ModVersion::factory()->recycle($mainMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency mod with a single version
            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $dependencyVersion = ModVersion::factory()->recycle($dependencyMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            // Manually create ONE dependency
            $dependency = Dependency::factory()->make([
                'dependable_id' => $mainModVersion->id,
                'dependent_mod_id' => $dependencyMod->id,
                'constraint' => '^2.0.0',
            ]);
            $dependency->saveQuietly(); // Skip observer

            // Create TWO identical resolved dependency records (simulating a data integrity issue)
            DependencyResolved::factory()->make([
                'dependable_id' => $mainModVersion->id,
                'dependency_id' => $dependency->id,
                'resolved_mod_version_id' => $dependencyVersion->id,
            ])->saveQuietly();

            DependencyResolved::factory()->make([
                'dependable_id' => $mainModVersion->id,
                'dependency_id' => $dependency->id,
                'resolved_mod_version_id' => $dependencyVersion->id,
            ])->saveQuietly();

            // Verify we have 2 duplicate resolved dependency records
            expect(DependencyResolved::query()->where('dependable_id', $mainModVersion->id)->count())->toBe(2);

            // latestDependenciesResolved should still return ONLY 1 unique entry (not duplicates)
            $mainModVersion->load('latestDependenciesResolved');
            $latest = $mainModVersion->latestDependenciesResolved;

            expect($latest)->toHaveCount(1)
                ->and($latest->pluck('id')->unique())
                ->toHaveCount(1)
                ->and($latest->first()->version)->toBe('2.0.0');
        });

        it('does not return duplicate entries from latestDependenciesResolved with multiple versions', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            // Create a mod version
            $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
            $mainModVersion = ModVersion::factory()->recycle($mainMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency mod with multiple versions
            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $dependencyVersion1 = ModVersion::factory()->recycle($dependencyMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependencyVersion2 = ModVersion::factory()->recycle($dependencyMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a single dependency
            $dependency = Dependency::factory()->recycle([$mainModVersion, $dependencyMod])->create(['constraint' => '>=1.0.0']);

            // The observer should create 2 resolved dependencies (one for each matching version)
            expect(DependencyResolved::query()->where('dependable_id', $mainModVersion->id)->count())->toBe(2);

            // latestDependenciesResolved should return ONLY 1 entry (the latest version)
            $mainModVersion->load('latestDependenciesResolved');
            $latest = $mainModVersion->latestDependenciesResolved;

            expect($latest)->toHaveCount(1)
                ->and($latest->pluck('version')->unique())
                ->toHaveCount(1)
                ->toContain($dependencyVersion2->version);
        });

        it('displays the latest resolved dependencies on the mod detail page', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $dependentMod1 = Mod::factory()->create();
            $dependentMod1Version1 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod1Version2 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            $dependentMod2 = Mod::factory()->create();
            $dependentMod2Version1 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod2Version2 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.1.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod2Version3 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.2.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod2Version4 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.2.1', 'spt_version_constraint' => '3.8.0']);

            $mod = Mod::factory()->create();
            $mainModVersion = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            Dependency::factory()->recycle([$mainModVersion, $dependentMod1])->create(['constraint' => '>=1.0.0']);
            Dependency::factory()->recycle([$mainModVersion, $dependentMod2])->create(['constraint' => '>=1.0.0']);

            // Test dependenciesResolved returns all resolved versions
            $mainModVersion->load('dependenciesResolved');
            expect($mainModVersion->dependenciesResolved)->toHaveCount(6)
                ->and($mainModVersion->dependenciesResolved->pluck('version'))
                ->toContain($dependentMod1Version1->version)
                ->toContain($dependentMod1Version2->version)
                ->toContain($dependentMod2Version1->version)
                ->toContain($dependentMod2Version2->version)
                ->toContain($dependentMod2Version3->version)
                ->toContain($dependentMod2Version4->version);

            // Test latestDependenciesResolved returns only the latest version per mod
            $mainModVersion->load('latestDependenciesResolved');
            expect($mainModVersion->latestDependenciesResolved)->toHaveCount(2)
                ->and($mainModVersion->latestDependenciesResolved->pluck('version'))
                ->toContain($dependentMod1Version2->version) // Latest version of dependentMod1
                ->toContain($dependentMod2Version4->version); // Latest version of dependentMod2

            $response = $this->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]));

            // The view shows latestDependenciesResolved in the Required Dependencies section
            $response->assertSee(__('Required Dependencies'))
                ->assertSee(__('The latest version of this mod requires the following mods to be installed as well.'))
                ->assertSee($dependentMod1->name)
                ->assertSee(__('Requires').' v'.$dependentMod1Version2->version)
                ->assertSee($dependentMod2->name)
                ->assertSee(__('Requires').' v'.$dependentMod2Version4->version);
        });
    });
});
