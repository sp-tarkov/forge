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
    $this->service = app(AddonVersionService::class);
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
    });
});
