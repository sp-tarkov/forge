<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Addon Version Constraint Resolution', function (): void {
    it('resolves compatible mod versions based on constraint', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create(['published_at' => now()]);

        // Create multiple mod versions
        $v1_0_0 = ModVersion::factory()->for($mod)->create(['version' => '1.0.0']);
        $v1_1_0 = ModVersion::factory()->for($mod)->create(['version' => '1.1.0']);
        $v1_2_0 = ModVersion::factory()->for($mod)->create(['version' => '1.2.0']);
        $v2_0_0 = ModVersion::factory()->for($mod)->create(['version' => '2.0.0']);

        $addon = Addon::factory()->for($mod)->for($user, 'owner')->published()->create();

        // Test caret constraint (^1.0.0 matches 1.x.x but not 2.x.x)
        $addonVersion = AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0']);

        $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();

        expect($compatibleIds)->toContain($v1_0_0->id)
            ->toContain($v1_1_0->id)
            ->toContain($v1_2_0->id)
            ->not->toContain($v2_0_0->id);
    });

    it('resolves compatible mod versions with tilde constraint', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create(['published_at' => now()]);

        // Create multiple mod versions
        $v1_0_0 = ModVersion::factory()->for($mod)->create(['version' => '1.0.0']);
        $v1_0_5 = ModVersion::factory()->for($mod)->create(['version' => '1.0.5']);
        $v1_1_0 = ModVersion::factory()->for($mod)->create(['version' => '1.1.0']);

        $addon = Addon::factory()->for($mod)->for($user, 'owner')->published()->create();

        // Test tilde constraint (~1.0.0 matches 1.0.x but not 1.1.x)
        $addonVersion = AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '~1.0.0']);

        $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();

        expect($compatibleIds)->toContain($v1_0_0->id)
            ->toContain($v1_0_5->id)
            ->not->toContain($v1_1_0->id);
    });

    it('resolves compatible mod versions with range constraint', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create(['published_at' => now()]);

        // Create multiple mod versions
        $v0_9_0 = ModVersion::factory()->for($mod)->create(['version' => '0.9.0']);
        $v1_0_0 = ModVersion::factory()->for($mod)->create(['version' => '1.0.0']);
        $v1_5_0 = ModVersion::factory()->for($mod)->create(['version' => '1.5.0']);
        $v2_0_0 = ModVersion::factory()->for($mod)->create(['version' => '2.0.0']);
        $v2_1_0 = ModVersion::factory()->for($mod)->create(['version' => '2.1.0']);

        $addon = Addon::factory()->for($mod)->for($user, 'owner')->published()->create();

        // Test range constraint (>=1.0.0 <2.0.0)
        $addonVersion = AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '>=1.0.0 <2.0.0']);

        $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();

        expect($compatibleIds)->not->toContain($v0_9_0->id)
            ->toContain($v1_0_0->id)
            ->toContain($v1_5_0->id)
            ->not->toContain($v2_0_0->id)
            ->not->toContain($v2_1_0->id);
    });

    it('updates compatible versions when constraint changes', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create(['published_at' => now()]);

        // Create multiple mod versions
        $v1_0_0 = ModVersion::factory()->for($mod)->create(['version' => '1.0.0']);
        $v2_0_0 = ModVersion::factory()->for($mod)->create(['version' => '2.0.0']);

        $addon = Addon::factory()->for($mod)->for($user, 'owner')->published()->create();
        $addonVersion = AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0']);

        // Initially should only match v1.0.0
        expect($addonVersion->compatibleModVersions->pluck('id')->toArray())
            ->toContain($v1_0_0->id)
            ->not->toContain($v2_0_0->id);

        // Update constraint
        $addonVersion->mod_version_constraint = '^2.0.0';
        $addonVersion->save();
        $addonVersion->refresh();

        // Now should only match v2.0.0
        expect($addonVersion->compatibleModVersions->pluck('id')->toArray())
            ->not->toContain($v1_0_0->id)
            ->toContain($v2_0_0->id);
    });

    it('clears compatible versions when addon is detached', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create(['published_at' => now()]);
        $modVersion = ModVersion::factory()->for($mod)->create(['version' => '1.0.0']);

        $addon = Addon::factory()->for($mod)->for($user, 'owner')->published()->create();
        $addonVersion = AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0']);

        // Should have compatible versions initially
        expect($addonVersion->compatibleModVersions)->not->toBeEmpty();

        // Detach the addon
        $addon->mod_id = null;
        $addon->detached_at = now();
        $addon->detached_by_user_id = $user->id;
        $addon->save();

        // Re-save the addon version to trigger observer and re-resolve constraints
        $addonVersion->touch();
        $addonVersion->save();
        $addonVersion->refresh();

        // Should have no compatible versions after detachment
        expect($addonVersion->compatibleModVersions)->toBeEmpty();
    });

    it('excludes disabled mod versions from compatibility', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create(['published_at' => now()]);

        // Create enabled and disabled mod versions
        $enabledVersion = ModVersion::factory()->for($mod)->create([
            'version' => '1.0.0',
            'disabled' => false,
        ]);
        $disabledVersion = ModVersion::factory()->for($mod)->create([
            'version' => '1.1.0',
            'disabled' => true,
        ]);

        $addon = Addon::factory()->for($mod)->for($user, 'owner')->published()->create();
        $addonVersion = AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0']);

        $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();

        expect($compatibleIds)->toContain($enabledVersion->id)
            ->not->toContain($disabledVersion->id);
    });

    it('excludes unpublished mod versions from compatibility', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create(['published_at' => now()]);

        // Create published and unpublished mod versions
        $publishedVersion = ModVersion::factory()->for($mod)->create(['version' => '1.0.0']);
        $unpublishedVersion = ModVersion::factory()->for($mod)->create([
            'version' => '1.1.0',
            'published_at' => null,
        ]);

        $addon = Addon::factory()->for($mod)->for($user, 'owner')->published()->create();
        $addonVersion = AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0']);

        $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();

        expect($compatibleIds)->toContain($publishedVersion->id)
            ->not->toContain($unpublishedVersion->id);
    });

    it('handles invalid semver constraints gracefully', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create(['published_at' => now()]);
        $modVersion = ModVersion::factory()->for($mod)->create(['version' => '1.0.0']);

        $addon = Addon::factory()->for($mod)->for($user, 'owner')->published()->create();

        // Create with invalid constraint (should not throw exception)
        $addonVersion = AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => 'invalid-constraint']);

        // Should have no compatible versions due to invalid constraint
        expect($addonVersion->compatibleModVersions)->toBeEmpty();
    });

    it('resolves compatible versions for exact version match', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create(['published_at' => now()]);

        // Create multiple mod versions
        $v1_0_0 = ModVersion::factory()->for($mod)->create(['version' => '1.0.0']);
        $v1_0_1 = ModVersion::factory()->for($mod)->create(['version' => '1.0.1']);

        $addon = Addon::factory()->for($mod)->for($user, 'owner')->published()->create();

        // Test exact version constraint
        $addonVersion = AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '1.0.0']);

        $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();

        expect($compatibleIds)->toContain($v1_0_0->id)
            ->not->toContain($v1_0_1->id);
    });

    it('resolves compatible versions with wildcard constraint', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create(['published_at' => now()]);

        // Create multiple mod versions
        $v1_0_0 = ModVersion::factory()->for($mod)->create(['version' => '1.0.0']);
        $v1_5_2 = ModVersion::factory()->for($mod)->create(['version' => '1.5.2']);
        $v2_0_0 = ModVersion::factory()->for($mod)->create(['version' => '2.0.0']);

        $addon = Addon::factory()->for($mod)->for($user, 'owner')->published()->create();

        // Test wildcard constraint
        $addonVersion = AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '1.*']);

        $compatibleIds = $addonVersion->compatibleModVersions->pluck('id')->toArray();

        expect($compatibleIds)->toContain($v1_0_0->id)
            ->toContain($v1_5_2->id)
            ->not->toContain($v2_0_0->id);
    });
});
