<?php

declare(strict_types=1);

use App\Enums\FikaCompatibility;
use App\Livewire\Page\ModVersion\Edit as ModVersionEdit;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

describe('Mod Version Edit Form', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);

        // Mock HTTP responses for download link validation
        Http::fake([
            'https://example.com/download.7z' => Http::response('', 200, [
                'content-type' => 'application/octet-stream',
                'content-length' => '1048576',
            ]),
            'https://example.com/download.zip' => Http::response('', 200, [
                'content-type' => 'application/octet-stream',
                'content-disposition' => 'attachment; filename="mod.7z"',
                'content-length' => '2097152',
            ]),
            '*' => Http::response('', 200, [
                'content-type' => 'application/octet-stream',
                'content-disposition' => 'attachment; filename="mod.7z"',
                'content-length' => '1048576',
            ]),
        ]);
    });

    describe('Form Validation', function (): void {
        it('requires all required fields to update a mod version', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $mod = Mod::factory()->create(['owner_id' => $user->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
            ]);

            Livewire::test(ModVersionEdit::class, ['mod' => $mod, 'modVersion' => $modVersion])
                ->set('version', '')
                ->set('description', '')
                ->set('link', '')
                ->set('sptVersionConstraint', '')
                ->set('virusTotalLinks', [])
                ->call('save')
                ->assertHasErrors(['version', 'description', 'link', 'sptVersionConstraint', 'virusTotalLinks']);
        });

        it('validates version format when editing', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $mod = Mod::factory()->create(['owner_id' => $user->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
            ]);

            Livewire::test(ModVersionEdit::class, ['mod' => $mod, 'modVersion' => $modVersion])
                ->set('version', 'invalid-version')
                ->set('description', 'Test description')
                ->set('link', 'https://example.com/download.zip')
                ->set('sptVersionConstraint', '~3.11.0')
                ->set('virusTotalLinks', [['url' => 'https://www.virustotal.com/test', 'label' => '']])
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
                ->set('virusTotalLinks', [
                    ['url' => 'https://www.virustotal.com/gui/file/abc123', 'label' => 'Test Scan'],
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

    describe('GUID Requirements', function (): void {
        beforeEach(function (): void {
            // Create required data
            $this->user = User::factory()->withMfa()->create();
            $this->license = License::factory()->create();
            $this->category = ModCategory::factory()->create();

            // Create SPT versions for testing
            SptVersion::factory()->create(['version' => '3.9.0']);
            SptVersion::factory()->create(['version' => '3.10.0']);
            SptVersion::factory()->create(['version' => '4.0.0']);
            SptVersion::factory()->create(['version' => '4.1.0']);
        });

        uses(RefreshDatabase::class);

        it('allows editing mod version with inline GUID save', function (): void {
            $this->actingAs($this->user);

            $mod = Mod::factory()->create([
                'owner_id' => $this->user->id,
                'guid' => '', // No GUID initially
                'license_id' => $this->license->id,
                'category_id' => $this->category->id,
            ]);

            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'version' => '1.0.0',
                'spt_version_constraint' => '~3.9.0', // Initially targeting SPT 3.x
            ]);

            $component = Livewire::test(ModVersionEdit::class, ['mod' => $mod, 'modVersion' => $modVersion])
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp));

            // Initially no GUID required
            expect($component->get('modGuidRequired'))->toBeFalse();

            // Change to target SPT 4.x
            $component->set('sptVersionConstraint', '>=4.0.0');
            expect($component->get('modGuidRequired'))->toBeTrue();

            // Save GUID inline
            $component->set('newModGuid', 'com.test.editguid')
                ->call('saveGuid')
                ->assertHasNoErrors();

            expect($component->get('guidSaved'))->toBeTrue();
            expect($component->get('modGuid'))->toBe('com.test.editguid');

            // Verify the mod was updated
            $mod->refresh();
            expect($mod->guid)->toBe('com.test.editguid');
        });

        it('validates GUID when editing mod version', function (): void {
            $this->actingAs($this->user);

            // Create a mod with an existing GUID for uniqueness test
            Mod::factory()->create([
                'owner_id' => $this->user->id,
                'guid' => 'com.existing.mod',
                'license_id' => $this->license->id,
                'category_id' => $this->category->id,
            ]);

            $mod = Mod::factory()->create([
                'owner_id' => $this->user->id,
                'guid' => '',
                'license_id' => $this->license->id,
                'category_id' => $this->category->id,
            ]);

            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'spt_version_constraint' => '>=4.0.0',
            ]);

            $component = Livewire::test(ModVersionEdit::class, ['mod' => $mod, 'modVersion' => $modVersion])
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp));

            // Test invalid format
            $component->set('newModGuid', 'Invalid GUID Format')
                ->call('saveGuid')
                ->assertHasErrors(['newModGuid' => 'regex']);

            // Test duplicate GUID
            $component->set('newModGuid', 'com.existing.mod')
                ->call('saveGuid')
                ->assertHasErrors(['newModGuid' => 'unique']);
        });

        it('does not require GUID when editing version if already saved inline', function (): void {
            $this->actingAs($this->user);

            // Mock HTTP responses for download link validation
            Http::fake([
                'https://example.com/mod.zip' => Http::response('', 200, [
                    'content-type' => 'application/octet-stream',
                    'content-disposition' => 'attachment; filename="mod.zip"',
                    'content-length' => '1048576',
                ]),
            ]);

            $mod = Mod::factory()->create([
                'owner_id' => $this->user->id,
                'guid' => '',
                'license_id' => $this->license->id,
                'category_id' => $this->category->id,
            ]);

            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'spt_version_constraint' => '>=4.0.0',
            ]);

            $component = Livewire::test(ModVersionEdit::class, ['mod' => $mod, 'modVersion' => $modVersion])
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('sptVersionConstraint', '>=4.0.0')
                ->set('newModGuid', 'com.test.editsave');

            // Save GUID inline first
            $component->call('saveGuid')
                ->assertHasNoErrors();

            expect($component->get('guidSaved'))->toBeTrue();

            // Now save the version without needing GUID validation
            $component->set('version', '2.0.0')
                ->set('description', 'Updated version')
                ->set('link', 'https://example.com/mod.zip')
                ->set('virusTotalLinks', [['url' => 'https://www.virustotal.com/gui/file/test', 'label' => '']])
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            // Verify version was updated
            $modVersion->refresh();
            expect($modVersion->version)->toBe('2.0.0');
        });

        it('allows updating fika compatibility status when editing a mod version', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            $mod = Mod::factory()->create(['owner_id' => $user->id]);
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'fika_compatibility' => FikaCompatibility::Incompatible,
            ]);

            Livewire::test(ModVersionEdit::class, ['mod' => $mod, 'modVersion' => $modVersion])
                ->assertSet('fikaCompatibilityStatus', 'incompatible')
                ->set('fikaCompatibilityStatus', 'compatible')
                ->set('virusTotalLinks', [
                    ['url' => 'https://www.virustotal.com/gui/file/abc123', 'label' => 'Test Scan'],
                ])
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            $modVersion->refresh();
            expect($modVersion->fika_compatibility->value)->toBe('compatible');
        });
    });
});
