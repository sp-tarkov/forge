<?php

declare(strict_types=1);

use App\Livewire\Page\ModVersion\Create as ModVersionCreate;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Livewire\Livewire;

describe('Mod Version Create Form', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    describe('Form Validation', function (): void {
        it('requires all required fields to create a mod version', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            Livewire::test(ModVersionCreate::class, ['mod' => $mod])
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->call('save')
                ->assertHasErrors(['version', 'description', 'link', 'sptVersionConstraint', 'virusTotalLink']);
        });

        it('validates version format', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            Livewire::test(ModVersionCreate::class, ['mod' => $mod])
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('version', 'invalid-version')
                ->set('description', 'Test description')
                ->set('link', 'https://example.com/download.zip')
                ->set('sptVersionConstraint', '~3.11.0')
                ->set('virusTotalLink', 'https://www.virustotal.com/test')
                ->call('save')
                ->assertHasErrors(['version']);
        });
    });

    describe('Basic Form Functionality', function (): void {
        it('allows creating a mod version with dependencies', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            // Create dependency mods with versions
            $dependencyMod1 = Mod::factory()->create();
            $dependencyVersion1 = ModVersion::factory()->create([
                'mod_id' => $dependencyMod1->id,
                'version' => '1.0.0',
            ]);
            $dependencyVersion2 = ModVersion::factory()->create([
                'mod_id' => $dependencyMod1->id,
                'version' => '1.1.0',
            ]);

            $dependencyMod2 = Mod::factory()->create();
            $dependencyVersion3 = ModVersion::factory()->create([
                'mod_id' => $dependencyMod2->id,
                'version' => '2.0.0',
            ]);

            // Test creating mod version with dependencies
            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod])
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('version', '1.0.0')
                ->set('description', 'Test version with dependencies')
                ->set('link', 'https://example.com/download.zip')
                ->set('sptVersionConstraint', '~3.11.0')
                ->set('virusTotalLink', 'https://www.virustotal.com/test');

            // Add dependencies using proper methods to trigger matching versions update
            $component->call('addDependency');
            $component->call('updateDependencyModId', 0, (string) $dependencyMod1->id);
            $component->call('updateDependencyConstraint', 0, '~1.0.0');

            $component->call('addDependency');
            $component->call('updateDependencyModId', 1, (string) $dependencyMod2->id);
            $component->call('updateDependencyConstraint', 1, '^2.0.0');

            $component->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            // Verify the mod version was created
            $modVersion = ModVersion::query()->where('mod_id', $mod->id)->first();
            expect($modVersion)->not->toBeNull();
            expect($modVersion->version)->toBe('1.0.0');

            // Verify dependencies were created
            expect($modVersion->dependencies)->toHaveCount(2);

            $dependency1 = $modVersion->dependencies->firstWhere('dependent_mod_id', $dependencyMod1->id);
            expect($dependency1)->not->toBeNull();
            expect($dependency1->constraint)->toBe('~1.0.0');

            $dependency2 = $modVersion->dependencies->firstWhere('dependent_mod_id', $dependencyMod2->id);
            expect($dependency2)->not->toBeNull();
            expect($dependency2->constraint)->toBe('^2.0.0');

            // Verify resolved dependencies were created by the observer
            $modVersion->load('resolvedDependencies');
            expect($modVersion->resolvedDependencies)->toHaveCount(2); // Should match versions 1.0.0 and 2.0.0

            // Check specific resolved versions
            $resolvedVersionIds = $modVersion->resolvedDependencies->pluck('id')->toArray();
            expect($resolvedVersionIds)->toContain($dependencyVersion1->id); // ~1.0.0 matches 1.0.0
            expect($resolvedVersionIds)->not->toContain($dependencyVersion2->id); // ~1.0.0 does not match 1.1.0
            expect($resolvedVersionIds)->toContain($dependencyVersion3->id); // ^2.0.0 matches 2.0.0
        });
    });

    describe('Dependency Management', function (): void {
        it('allows adding and removing dependencies dynamically', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Initially should have no dependencies
            expect($component->get('dependencies'))->toHaveCount(0);

            // Add a dependency
            $component->call('addDependency');
            expect($component->get('dependencies'))->toHaveCount(1);
            $firstDependency = $component->get('dependencies')[0];
            expect($firstDependency)->toHaveKeys(['id', 'modId', 'constraint']);
            expect($firstDependency['modId'])->toBe('');
            expect($firstDependency['constraint'])->toBe('');
            expect($firstDependency['id'])->toBeString();

            // Add another dependency
            $component->call('addDependency');
            expect($component->get('dependencies'))->toHaveCount(2);

            // Remove the first dependency
            $component->call('removeDependency', 0);
            expect($component->get('dependencies'))->toHaveCount(1);
        });

        it('maintains correct wire model bindings after removing middle dependency', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            // Create dependency mods
            $dependencyMod1 = Mod::factory()->create(['name' => 'First Dependency']);
            $dependencyMod2 = Mod::factory()->create(['name' => 'Second Dependency']);
            $dependencyMod3 = Mod::factory()->create(['name' => 'Third Dependency']);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Add three dependencies
            $component->call('addDependency');
            $component->call('addDependency');
            $component->call('addDependency');

            // Set dependency values using the updateDependencyModId method
            $component->call('updateDependencyModId', 0, (string) $dependencyMod1->id);
            $component->call('updateDependencyModId', 1, (string) $dependencyMod2->id);
            $component->call('updateDependencyModId', 2, (string) $dependencyMod3->id);

            // Set constraints
            $dependencies = $component->get('dependencies');
            $dependencies[0]['constraint'] = '~1.0.0';
            $dependencies[1]['constraint'] = '~2.0.0';
            $dependencies[2]['constraint'] = '~3.0.0';
            $component->set('dependencies', $dependencies);

            // Verify initial state
            expect($component->get('dependencies'))->toHaveCount(3);

            // Remove the middle dependency
            $component->call('removeDependency', 1);

            // Verify the array was reindexed correctly
            $remainingDependencies = $component->get('dependencies');
            expect($remainingDependencies)->toHaveCount(2);
            expect($remainingDependencies[0]['modId'])->toBe((string) $dependencyMod1->id);
            expect($remainingDependencies[1]['modId'])->toBe((string) $dependencyMod3->id);

            // Now try to update the second dependency (which was previously index 2, now index 1)
            // This should work without throwing an undefined array key error
            $component->call('updateDependencyModId', 1, (string) $dependencyMod2->id);

            // Verify the update worked
            $updatedDependencies = $component->get('dependencies');
            expect($updatedDependencies[1]['modId'])->toBe((string) $dependencyMod2->id);
        });

        it('allows updating constraints after dependency removal', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            // Create dependency mods
            $dependencyMod1 = Mod::factory()->create(['name' => 'First Dependency']);
            $dependencyMod2 = Mod::factory()->create(['name' => 'Second Dependency']);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Add two dependencies
            $component->call('addDependency');
            $component->call('addDependency');

            // Set initial values
            $component->call('updateDependencyModId', 0, (string) $dependencyMod1->id);
            $component->call('updateDependencyModId', 1, (string) $dependencyMod2->id);

            $dependencies = $component->get('dependencies');
            $dependencies[0]['constraint'] = '~1.0.0';
            $dependencies[1]['constraint'] = '~2.0.0';
            $component->set('dependencies', $dependencies);

            // Remove the first dependency
            $component->call('removeDependency', 0);

            // Update the constraint of what is now the first (previously second) dependency
            $dependencies = $component->get('dependencies');
            $dependencies[0]['constraint'] = '~3.0.0';
            $component->set('dependencies', $dependencies);

            // Verify the update worked
            $updatedDependencies = $component->get('dependencies');
            expect($updatedDependencies[0]['modId'])->toBe((string) $dependencyMod2->id);
            expect($updatedDependencies[0]['constraint'])->toBe('~3.0.0');
        });

        it('removes the correct dependency when remove button is clicked', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            // Create dependency mods
            $dependencyMod1 = Mod::factory()->create(['name' => 'First Dependency']);
            $dependencyMod2 = Mod::factory()->create(['name' => 'Second Dependency']);
            $dependencyMod3 = Mod::factory()->create(['name' => 'Third Dependency']);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Add three dependencies
            $component->call('addDependency');
            $component->call('addDependency');
            $component->call('addDependency');

            // Set dependency values
            $dependencies = $component->get('dependencies');
            $dependencies[0]['modId'] = (string) $dependencyMod1->id;
            $dependencies[0]['constraint'] = '~1.0.0';
            $dependencies[1]['modId'] = (string) $dependencyMod2->id;
            $dependencies[1]['constraint'] = '~2.0.0';
            $dependencies[2]['modId'] = (string) $dependencyMod3->id;
            $dependencies[2]['constraint'] = '~3.0.0';
            $component->set('dependencies', $dependencies);

            // Verify we have 3 dependencies
            expect($component->get('dependencies'))->toHaveCount(3);

            // Remove the middle dependency (index 1)
            $component->call('removeDependency', 1);

            // Verify we now have 2 dependencies
            expect($component->get('dependencies'))->toHaveCount(2);

            // Verify the correct dependencies remain
            $remainingDependencies = $component->get('dependencies');
            expect($remainingDependencies[0]['modId'])->toBe((string) $dependencyMod1->id);
            expect($remainingDependencies[0]['constraint'])->toBe('~1.0.0');
            expect($remainingDependencies[1]['modId'])->toBe((string) $dependencyMod3->id);
            expect($remainingDependencies[1]['constraint'])->toBe('~3.0.0');
        });

        it('removes the first dependency correctly', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            // Create dependency mods
            $dependencyMod1 = Mod::factory()->create(['name' => 'First Dependency']);
            $dependencyMod2 = Mod::factory()->create(['name' => 'Second Dependency']);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Add two dependencies
            $component->call('addDependency');
            $component->call('addDependency');

            // Set dependency values
            $dependencies = $component->get('dependencies');
            $dependencies[0]['modId'] = (string) $dependencyMod1->id;
            $dependencies[0]['constraint'] = '~1.0.0';
            $dependencies[1]['modId'] = (string) $dependencyMod2->id;
            $dependencies[1]['constraint'] = '~2.0.0';
            $component->set('dependencies', $dependencies);

            // Remove the first dependency (index 0)
            $component->call('removeDependency', 0);

            // Verify only the second dependency remains
            expect($component->get('dependencies'))->toHaveCount(1);
            $remainingDependencies = $component->get('dependencies');
            expect($remainingDependencies[0]['modId'])->toBe((string) $dependencyMod2->id);
            expect($remainingDependencies[0]['constraint'])->toBe('~2.0.0');
        });

        it('removes the last dependency correctly', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            // Create dependency mods
            $dependencyMod1 = Mod::factory()->create(['name' => 'First Dependency']);
            $dependencyMod2 = Mod::factory()->create(['name' => 'Second Dependency']);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Add two dependencies
            $component->call('addDependency');
            $component->call('addDependency');

            // Set dependency values
            $dependencies = $component->get('dependencies');
            $dependencies[0]['modId'] = (string) $dependencyMod1->id;
            $dependencies[0]['constraint'] = '~1.0.0';
            $dependencies[1]['modId'] = (string) $dependencyMod2->id;
            $dependencies[1]['constraint'] = '~2.0.0';
            $component->set('dependencies', $dependencies);

            // Remove the last dependency (index 1)
            $component->call('removeDependency', 1);

            // Verify only the first dependency remains
            expect($component->get('dependencies'))->toHaveCount(1);
            $remainingDependencies = $component->get('dependencies');
            expect($remainingDependencies[0]['modId'])->toBe((string) $dependencyMod1->id);
            expect($remainingDependencies[0]['constraint'])->toBe('~1.0.0');
        });

        it('maintains unique IDs for dependencies after removal', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Add three dependencies
            $component->call('addDependency');
            $component->call('addDependency');
            $component->call('addDependency');

            // Get the initial IDs
            $initialDependencies = $component->get('dependencies');
            $firstId = $initialDependencies[0]['id'];
            $thirdId = $initialDependencies[2]['id'];

            // Remove the middle dependency
            $component->call('removeDependency', 1);

            // Verify the IDs are preserved for remaining dependencies
            $remainingDependencies = $component->get('dependencies');
            expect($remainingDependencies[0]['id'])->toBe($firstId);
            expect($remainingDependencies[1]['id'])->toBe($thirdId);
        });

        it('shows matching dependency versions when constraint is updated', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            // Create dependency mod with multiple versions
            $dependencyMod = Mod::factory()->create();
            ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '1.0.0',
            ]);
            ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '1.1.0',
            ]);
            ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '2.0.0',
            ]);

            // Test that matching versions are updated when constraint changes
            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod])
                ->set('dependencies.0.modId', (string) $dependencyMod->id)
                ->set('dependencies.0.constraint', '^1.0.0');

            // Check that matching versions are set correctly
            // ^1.0.0 matches 1.0.0, 1.1.0, but not 2.0.0
            expect($component->get('matchingDependencyVersions')[0])->toHaveCount(2);
            $versions = collect($component->get('matchingDependencyVersions')[0])->pluck('version')->toArray();
            expect($versions)->toContain('1.0.0');
            expect($versions)->toContain('1.1.0');
            expect($versions)->not->toContain('2.0.0');
        });

        it('prevents self-dependency through UI', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id, 'name' => 'Test Mod']);

            // Create another mod to ensure there are options available
            $otherMod = Mod::factory()->create(['name' => 'Other Mod']);

            // Test that the current mod is not available in the dependency selection
            // The blade template uses Mod::where('id', '!=', $mod->id) to exclude self
            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Create a version for the mod to match against
            ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'published_at' => now(),
            ]);

            // Manually check that setting self-dependency would be invalid
            // This simulates a malicious attempt to bypass the UI restriction
            $component->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('version', '1.0.1')
                ->set('description', 'Test version')
                ->set('link', 'https://example.com/download.zip')
                ->set('sptVersionConstraint', '~3.11.0')
                ->set('virusTotalLink', 'https://www.virustotal.com/test');

            // Add self-dependency using methods
            $component->call('addDependency');
            $component->call('updateDependencyModId', 0, (string) $mod->id);
            $component->call('updateDependencyConstraint', 0, '~1.0.0');

            $component->call('save')
                ->assertRedirect(); // It will succeed but won't create a self-dependency

            // Verify that no self-dependency was created
            $modVersion = ModVersion::query()->where('mod_id', $mod->id)->first();
            expect($modVersion)->not->toBeNull();

            // Check that no dependency pointing to self was created
            $selfDependency = $modVersion->dependencies()->where('dependent_mod_id', $mod->id)->first();
            expect($selfDependency)->toBeNull();
        });
    });

    describe('Validation', function (): void {
        it('validates that mod and constraint are both required when one is filled', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            // Create a dependency mod
            $dependencyMod = Mod::factory()->create(['name' => 'Test Dependency']);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Add a dependency
            $component->call('addDependency');

            // Set only the mod ID without a constraint
            $component->call('updateDependencyModId', 0, (string) $dependencyMod->id);

            // Fill in required fields for the mod version
            $component->set('version', '1.0.0');
            $component->set('description', 'Test description');
            $component->set('link', 'https://example.com/mod.zip');
            $component->set('sptVersionConstraint', '~3.9.0');
            $component->set('virusTotalLink', 'https://www.virustotal.com/test');

            // Try to save
            $component->call('save');

            // Should have validation error for missing constraint
            $component->assertHasErrors(['dependencies.0.constraint' => 'required']);
        });

        it('validates that constraint requires a mod to be selected', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Add a dependency
            $component->call('addDependency');

            // Set only the constraint without a mod
            $dependencies = $component->get('dependencies');
            $dependencies[0]['constraint'] = '~1.0.0';
            $component->set('dependencies', $dependencies);

            // Fill in required fields for the mod version
            $component->set('version', '1.0.0');
            $component->set('description', 'Test description');
            $component->set('link', 'https://example.com/mod.zip');
            $component->set('sptVersionConstraint', '~3.9.0');
            $component->set('virusTotalLink', 'https://www.virustotal.com/test');

            // Try to save
            $component->call('save');

            // Should have validation error for missing mod
            $component->assertHasErrors(['dependencies.0.modId' => 'required']);
        });

        it('validates that dependencies must have matching versions', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            // Create a dependency mod with a specific version
            $dependencyMod = Mod::factory()->create(['name' => 'Test Dependency']);
            ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '1.0.0',
                'published_at' => now(),
            ]);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Add a dependency
            $component->call('addDependency');

            // Set mod and constraint that won't match any versions
            $component->call('updateDependencyModId', 0, (string) $dependencyMod->id);
            $component->call('updateDependencyConstraint', 0, '~2.0.0'); // No 2.x versions exist

            // Fill in required fields for the mod version
            $component->set('version', '1.0.0');
            $component->set('description', 'Test description');
            $component->set('link', 'https://example.com/mod.zip');
            $component->set('sptVersionConstraint', '~3.9.0');
            $component->set('virusTotalLink', 'https://www.virustotal.com/test');

            // Try to save
            $component->call('save');

            // Should have validation error for no matching versions
            $component->assertHasErrors(['dependencies.0.constraint']);
        });

        it('allows saving when dependency has matching versions', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            // Create a dependency mod with versions
            $dependencyMod = Mod::factory()->create(['name' => 'Test Dependency']);
            ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '1.0.0',
                'published_at' => now(),
            ]);
            ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '1.0.1',
                'published_at' => now(),
            ]);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Add a dependency
            $component->call('addDependency');

            // Set mod and constraint that will match versions
            $component->call('updateDependencyModId', 0, (string) $dependencyMod->id);
            $component->call('updateDependencyConstraint', 0, '~1.0.0');

            // Fill in required fields for the mod version
            $component->set('version', '1.0.0');
            $component->set('description', 'Test description');
            $component->set('link', 'https://example.com/mod.zip');
            $component->set('sptVersionConstraint', '~3.9.0');
            $component->set('virusTotalLink', 'https://www.virustotal.com/test');

            // Try to save
            $component->call('save');

            // Should not have validation errors
            $component->assertHasNoErrors();

            // Verify the mod version was created
            $this->assertDatabaseHas('mod_versions', [
                'mod_id' => $mod->id,
                'version' => '1.0.0',
            ]);
        });

        it('allows empty dependencies without validation errors', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            $component = Livewire::test(ModVersionCreate::class, ['mod' => $mod]);

            // Add a dependency but leave it empty
            $component->call('addDependency');

            // Fill in required fields for the mod version
            $component->set('version', '1.0.0');
            $component->set('description', 'Test description');
            $component->set('link', 'https://example.com/mod.zip');
            $component->set('sptVersionConstraint', '~3.9.0');
            $component->set('virusTotalLink', 'https://www.virustotal.com/test');

            // Try to save
            $component->call('save');

            // Should not have validation errors - empty dependencies are allowed
            $component->assertHasNoErrors();

            // Verify the mod version was created without dependencies
            $this->assertDatabaseHas('mod_versions', [
                'mod_id' => $mod->id,
                'version' => '1.0.0',
            ]);

            $modVersion = ModVersion::query()->where('mod_id', $mod->id)->first();
            expect($modVersion->dependencies)->toHaveCount(0);
        });

        it('validates dependency constraint format', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);

            // Create dependency mod
            $dependencyMod = Mod::factory()->create();

            // Test creating mod version with invalid dependency constraint
            Livewire::test(ModVersionCreate::class, ['mod' => $mod])
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('version', '1.0.0')
                ->set('description', 'Test version')
                ->set('link', 'https://example.com/download.zip')
                ->set('sptVersionConstraint', '~3.11.0')
                ->set('virusTotalLink', 'https://www.virustotal.com/test')
                ->set('dependencies', [
                    ['modId' => (string) $dependencyMod->id, 'constraint' => 'invalid-constraint'],
                ])
                ->call('save')
                ->assertHasErrors(['dependencies.0.constraint']);
        });
    });

    describe('Dependency Resolution', function (): void {
        it('creates resolved dependencies automatically via observer', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();

            // Create dependency mod with versions
            $dependencyMod = Mod::factory()->create();
            $version1 = ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '1.0.0',
                'published_at' => now(),
            ]);
            $version2 = ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '1.1.0',
                'published_at' => now(),
            ]);
            $version3 = ModVersion::factory()->create([
                'mod_id' => $dependencyMod->id,
                'version' => '2.0.0',
                'published_at' => now(),
            ]);

            // Create a mod version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);
            $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id]);

            // Add a dependency with constraint ~1.0.0 (matches 1.0.x but not 1.1.x)
            $dependency = $modVersion->dependencies()->create([
                'dependent_mod_id' => $dependencyMod->id,
                'constraint' => '~1.0.0',
            ]);

            // The observer should have automatically resolved dependencies
            $modVersion->load('resolvedDependencies');

            // ~1.0.0 matches only 1.0.x versions, not 1.1.x
            expect($modVersion->resolvedDependencies)->toHaveCount(1);

            $resolvedVersionIds = $modVersion->resolvedDependencies->pluck('id')->toArray();
            expect($resolvedVersionIds)->toContain($version1->id);
            expect($resolvedVersionIds)->not->toContain($version2->id);
            expect($resolvedVersionIds)->not->toContain($version3->id);
        });
    });
});
