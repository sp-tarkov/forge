<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Services\AddonVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = resolve(AddonVersionService::class);
    $this->user = User::factory()->withMfa()->create();
    $this->mod = Mod::factory()->for($this->user, 'owner')->create(['published_at' => now()]);
    $this->addon = Addon::factory()->for($this->mod)->for($this->user, 'owner')->published()->create();
});

describe('AddonVersionService', function (): void {
    describe('resolve method', function (): void {
        it('resolves compatible mod versions based on semver constraint', function (): void {
            // Create mod versions
            $v1_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.0']);
            $v1_1_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.1.0']);
            $v2_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '2.0.0']);

            // Create addon version with caret constraint
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '^1.0.0']);

            // Resolve using service
            $this->service->resolve($addonVersion);
            $addonVersion->refresh();

            $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();
            expect($compatibleIds)->toContain($v1_0_0->id)
                ->toContain($v1_1_0->id)
                ->not->toContain($v2_0_0->id);
        });

        it('handles tilde constraints correctly', function (): void {
            // Create mod versions
            $v1_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.0']);
            $v1_0_5 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.5']);
            $v1_1_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.1.0']);

            // Create addon version with tilde constraint
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '~1.0.0']);

            // Resolve using service
            $this->service->resolve($addonVersion);
            $addonVersion->refresh();

            $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();
            expect($compatibleIds)->toContain($v1_0_0->id)
                ->toContain($v1_0_5->id)
                ->not->toContain($v1_1_0->id);
        });

        it('handles range constraints correctly', function (): void {
            // Create mod versions
            $v0_9_0 = ModVersion::factory()->for($this->mod)->create(['version' => '0.9.0']);
            $v1_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.0']);
            $v1_5_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.5.0']);
            $v2_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '2.0.0']);

            // Create addon version with range constraint
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '>=1.0.0 <2.0.0']);

            // Resolve using service
            $this->service->resolve($addonVersion);
            $addonVersion->refresh();

            $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();
            expect($compatibleIds)->not->toContain($v0_9_0->id)
                ->toContain($v1_0_0->id)
                ->toContain($v1_5_0->id)
                ->not->toContain($v2_0_0->id);
        });

        it('clears all compatible versions when addon has no parent mod', function (): void {
            // Create mod version
            $modVersion = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.0']);

            // Create addon version
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '^1.0.0']);

            // Initially should have compatible versions
            $this->service->resolve($addonVersion);
            $addonVersion->refresh();
            expect($addonVersion->compatibleModVersions)->not->toBeEmpty();

            // Remove parent mod
            $this->addon->mod_id = null;
            $this->addon->save();

            // Reload the addon version to get updated addon relationship
            $addonVersion->load('addon');

            // Resolve again
            $this->service->resolve($addonVersion);
            $addonVersion->refresh();

            // Should have no compatible versions
            expect($addonVersion->compatibleModVersions)->toBeEmpty();
        });

        it('filters out disabled mod versions', function (): void {
            // Create enabled and disabled mod versions
            $enabledVersion = ModVersion::factory()->for($this->mod)->create([
                'version' => '1.0.0',
                'disabled' => false,
            ]);
            $disabledVersion = ModVersion::factory()->for($this->mod)->create([
                'version' => '1.1.0',
                'disabled' => true,
            ]);

            // Create addon version
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '^1.0.0']);

            // Resolve using service
            $this->service->resolve($addonVersion);
            $addonVersion->refresh();

            $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();
            expect($compatibleIds)->toContain($enabledVersion->id)
                ->not->toContain($disabledVersion->id);
        });

        it('filters out unpublished mod versions', function (): void {
            // Create published and unpublished mod versions
            $publishedVersion = ModVersion::factory()->for($this->mod)->create([
                'version' => '1.0.0',
                'published_at' => now(),
            ]);
            $unpublishedVersion = ModVersion::factory()->for($this->mod)->create([
                'version' => '1.1.0',
                'published_at' => null,
            ]);

            // Create addon version
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '^1.0.0']);

            // Resolve using service
            $this->service->resolve($addonVersion);
            $addonVersion->refresh();

            $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();
            expect($compatibleIds)->toContain($publishedVersion->id)
                ->not->toContain($unpublishedVersion->id);
        });

        it('handles invalid constraints gracefully', function (): void {
            // Create mod version
            ModVersion::factory()->for($this->mod)->create(['version' => '1.0.0']);

            // Create addon version with invalid constraint
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => 'not-a-valid-constraint']);

            // Should not throw exception - just resolve silently
            $this->service->resolve($addonVersion);

            $addonVersion->refresh();
            // Should have no compatible versions due to invalid constraint
            expect($addonVersion->compatibleModVersions)->toBeEmpty();
        });

        it('handles exact version constraints', function (): void {
            // Create mod versions
            $v1_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.0']);
            $v1_0_1 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.1']);

            // Create addon version with exact constraint
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '1.0.0']);

            // Resolve using service
            $this->service->resolve($addonVersion);
            $addonVersion->refresh();

            $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();
            expect($compatibleIds)->toContain($v1_0_0->id)
                ->not->toContain($v1_0_1->id);
        });

        it('handles wildcard constraints', function (): void {
            // Create mod versions
            $v1_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.0']);
            $v1_5_2 = ModVersion::factory()->for($this->mod)->create(['version' => '1.5.2']);
            $v2_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '2.0.0']);

            // Create addon version with wildcard constraint
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '1.*']);

            // Resolve using service
            $this->service->resolve($addonVersion);
            $addonVersion->refresh();

            $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();
            expect($compatibleIds)->toContain($v1_0_0->id)
                ->toContain($v1_5_2->id)
                ->not->toContain($v2_0_0->id);
        });

        it('resolves multiple matching versions', function (): void {
            // Create multiple matching mod versions
            $v1_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.0']);
            $v1_0_1 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.1']);
            $v1_0_2 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.2']);
            $v1_1_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.1.0']);

            // Create addon version with constraint that matches multiple versions
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '~1.0.0']);

            // Resolve using service
            $this->service->resolve($addonVersion);
            $addonVersion->refresh();

            // Should match all 1.0.x versions
            expect($addonVersion->compatibleModVersions)->toHaveCount(3);
            $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();
            expect($compatibleIds)->toContain($v1_0_0->id)
                ->toContain($v1_0_1->id)
                ->toContain($v1_0_2->id)
                ->not->toContain($v1_1_0->id);
        });
    });

    describe('observer integration', function (): void {
        it('automatically resolves constraints when addon version is saved', function (): void {
            // Create mod versions
            $v1_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.0']);
            $v2_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '2.0.0']);

            // Create addon version - observer should trigger resolution
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '^1.0.0']);

            // Check that resolution happened automatically
            $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();
            expect($compatibleIds)->toContain($v1_0_0->id)
                ->not->toContain($v2_0_0->id);
        });

        it('re-resolves constraints when constraint is updated', function (): void {
            // Create mod versions
            $v1_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.0']);
            $v2_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '2.0.0']);

            // Create addon version
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '^1.0.0']);

            // Initially should match v1.0.0
            expect($addonVersion->compatibleModVersions->pluck('id')->toArray())
                ->toContain($v1_0_0->id)
                ->not->toContain($v2_0_0->id);

            // Update constraint
            $addonVersion->mod_version_constraint = '^2.0.0';
            $addonVersion->save();
            $addonVersion->refresh();

            // Should now match v2.0.0
            expect($addonVersion->compatibleModVersions->pluck('id')->toArray())
                ->not->toContain($v1_0_0->id)
                ->toContain($v2_0_0->id);
        });

        it('re-resolves addon versions when new mod version is created', function (): void {
            // Create initial mod version
            $v2_0_5 = ModVersion::factory()->for($this->mod)->create(['version' => '2.0.5']);

            // Create addon version with tilde constraint that should match 2.0.x versions
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '~2.0.5']);

            // Initially should match v2.0.5
            $addonVersion->refresh();
            expect($addonVersion->compatibleModVersions->pluck('id')->toArray())
                ->toContain($v2_0_5->id);

            // Create new mod version 2.0.6 - this should automatically trigger re-resolution
            $v2_0_6 = ModVersion::factory()->for($this->mod)->create(['version' => '2.0.6']);

            // Addon version should now include both 2.0.5 and 2.0.6
            $addonVersion->refresh();
            expect($addonVersion->compatibleModVersions->pluck('id')->toArray())
                ->toContain($v2_0_5->id)
                ->toContain($v2_0_6->id);
        });

        it('re-resolves addon versions when mod version is deleted', function (): void {
            // Create mod versions
            $v2_0_5 = ModVersion::factory()->for($this->mod)->create(['version' => '2.0.5']);
            $v2_0_6 = ModVersion::factory()->for($this->mod)->create(['version' => '2.0.6']);

            // Create addon version with tilde constraint
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '~2.0.5']);

            // Should match both versions initially
            $addonVersion->refresh();
            expect($addonVersion->compatibleModVersions)->toHaveCount(2);

            // Delete one version - should trigger re-resolution
            $v2_0_6->delete();

            // Addon version should now only include 2.0.5
            $addonVersion->refresh();
            expect($addonVersion->compatibleModVersions)->toHaveCount(1);
            expect($addonVersion->compatibleModVersions->pluck('id')->toArray())
                ->toContain($v2_0_5->id);
        });

        it('re-resolves addon versions when mod version is updated', function (): void {
            // Create initial mod version
            $v2_0_5 = ModVersion::factory()->for($this->mod)->create(['version' => '2.0.5']);

            // Create addon version with tilde constraint
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '~2.0.5']);

            // Should match v2.0.5 initially
            $addonVersion->refresh();
            expect($addonVersion->compatibleModVersions->pluck('id')->toArray())
                ->toContain($v2_0_5->id);

            // Update the mod version to disable it
            $v2_0_5->disabled = true;
            $v2_0_5->save();

            // Addon version should no longer have any compatible versions
            $addonVersion->refresh();
            expect($addonVersion->compatibleModVersions)->toBeEmpty();
        });

        it('only re-resolves addon versions for the same mod', function (): void {
            // Create another mod
            $otherMod = Mod::factory()->for($this->user, 'owner')->create(['published_at' => now()]);
            $otherAddon = Addon::factory()->for($otherMod)->for($this->user, 'owner')->published()->create();

            // Create mod versions for both mods
            $v1_0_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.0.0']);
            $otherV1_0_0 = ModVersion::factory()->for($otherMod)->create(['version' => '1.0.0']);

            // Create addon versions for both addons
            $addonVersion = AddonVersion::factory()
                ->for($this->addon)
                ->create(['mod_version_constraint' => '^1.0.0']);

            $otherAddonVersion = AddonVersion::factory()
                ->for($otherAddon)
                ->create(['mod_version_constraint' => '^1.0.0']);

            // Verify initial state
            $addonVersion->refresh();
            $otherAddonVersion->refresh();
            expect($addonVersion->compatibleModVersions)->toHaveCount(1);
            expect($otherAddonVersion->compatibleModVersions)->toHaveCount(1);

            // Create new version for first mod - should only affect first addon
            $v1_1_0 = ModVersion::factory()->for($this->mod)->create(['version' => '1.1.0']);

            $addonVersion->refresh();
            $otherAddonVersion->refresh();

            // First addon should have 2 compatible versions
            expect($addonVersion->compatibleModVersions)->toHaveCount(2);
            // Other addon should still have 1 compatible version
            expect($otherAddonVersion->compatibleModVersions)->toHaveCount(1);
        });
    });
});
