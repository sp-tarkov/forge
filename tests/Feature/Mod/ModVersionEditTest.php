<?php

declare(strict_types=1);

use App\Livewire\Page\ModVersion\Edit as ModVersionEdit;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Livewire\Livewire;

describe('Mod Version Edit Form', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    describe('Form Validation', function (): void {
        it('requires all required fields to update a mod version', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $mod = Mod::factory()->create(['owner_id' => $user->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'virus_total_link' => 'https://www.virustotal.com/test',
            ]);

            Livewire::test(ModVersionEdit::class, ['mod' => $mod, 'modVersion' => $modVersion])
                ->set('version', '')
                ->set('description', '')
                ->set('link', '')
                ->set('sptVersionConstraint', '')
                ->set('virusTotalLink', '')
                ->call('save')
                ->assertHasErrors(['version', 'description', 'link', 'sptVersionConstraint', 'virusTotalLink']);
        });

        it('validates version format when editing', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $mod = Mod::factory()->create(['owner_id' => $user->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'virus_total_link' => 'https://www.virustotal.com/test',
            ]);

            Livewire::test(ModVersionEdit::class, ['mod' => $mod, 'modVersion' => $modVersion])
                ->set('version', 'invalid-version')
                ->set('description', 'Test description')
                ->set('link', 'https://example.com/download.zip')
                ->set('sptVersionConstraint', '~3.11.0')
                ->set('virusTotalLink', 'https://www.virustotal.com/test')
                ->call('save')
                ->assertHasErrors(['version']);
        });

        it('validates dependencies when editing a mod version', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod and version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
            ]);

            // Create a dependency mod
            $dependencyMod = Mod::factory()->create(['name' => 'Test Dependency']);

            $component = Livewire::test(ModVersionEdit::class, ['mod' => $mod, 'modVersion' => $modVersion]);

            // Add a dependency
            $component->call('addDependency');

            // Set only the mod ID without a constraint
            $component->call('updateDependencyModId', 0, (string) $dependencyMod->id);

            // Try to save
            $component->call('save');

            // Should have validation error for missing constraint
            $component->assertHasErrors(['dependencies.0.constraint' => 'required']);
        });
    });

    describe('Dependency Management', function (): void {
        it('allows editing a mod version to update dependencies', function (): void {
            // Create test user with MFA
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            // Create the mod that will own the version
            $mod = Mod::factory()->create(['owner_id' => $user->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'virus_total_link' => 'https://www.virustotal.com/test',
            ]);

            // Create dependency mods with versions
            $dependencyMod1 = Mod::factory()->create();
            ModVersion::factory()->create([
                'mod_id' => $dependencyMod1->id,
                'version' => '1.0.0',
            ]);

            $dependencyMod2 = Mod::factory()->create();
            ModVersion::factory()->create([
                'mod_id' => $dependencyMod2->id,
                'version' => '2.0.0',
            ]);

            // Initially create one dependency
            $modVersion->dependencies()->create([
                'dependent_mod_id' => $dependencyMod1->id,
                'constraint' => '~1.0.0',
            ]);

            // Test editing mod version to update dependencies
            // Note: Edit component loads existing values in mount(), so we don't need to set all fields
            Livewire::test(ModVersionEdit::class, ['mod' => $mod, 'modVersion' => $modVersion])
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('dependencies', [
                    ['modId' => (string) $dependencyMod2->id, 'constraint' => '^2.0.0'],
                ])
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            // Refresh and verify dependencies were updated
            $modVersion->refresh();
            $modVersion->load('dependencies', 'resolvedDependencies');

            expect($modVersion->dependencies)->toHaveCount(1);

            $dependency = $modVersion->dependencies->first();
            expect($dependency->dependent_mod_id)->toBe($dependencyMod2->id);
            expect($dependency->constraint)->toBe('^2.0.0');

            // Verify resolved dependencies were updated
            expect($modVersion->resolvedDependencies)->toHaveCount(1);
            expect($modVersion->resolvedDependencies->first()->mod_id)->toBe($dependencyMod2->id);
        });
    });
});
