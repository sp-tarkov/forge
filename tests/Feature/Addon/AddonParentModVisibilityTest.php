<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Addon Parent Mod Visibility', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    });

    it('hides addon from guests when parent mod has no versions', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertForbidden();
    });

    it('hides addon from normal users when parent mod has no versions', function (): void {
        $normalUser = User::factory()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->actingAs($normalUser)->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertForbidden();
    });

    it('shows addon to owner when parent mod has no versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'owner_id' => $this->user->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->actingAs($this->user)->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSuccessful();
        $response->assertSee($addon->name);
    });

    it('shows addon to author when parent mod has no versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'owner_id' => $owner->id]);
        $addon->additionalAuthors()->attach($this->user->id);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->actingAs($this->user)->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSuccessful();
        $response->assertSee($addon->name);
    });

    it('excludes addon from parent mod addon listing when mod has no versions', function (): void {
        $modWithVersions = Mod::factory()->create();
        $sptVersion = SptVersion::factory()->create(['version' => '3.12.1']);
        ModVersion::factory()->create([
            'mod_id' => $modWithVersions->id,
            'spt_version_constraint' => '^3.12.0',
        ]);
        $visibleAddon = Addon::factory()->create(['mod_id' => $modWithVersions->id]);
        AddonVersion::factory()->create(['addon_id' => $visibleAddon->id]);

        $modWithoutVersions = Mod::factory()->create();
        $hiddenAddon = Addon::factory()->create(['mod_id' => $modWithoutVersions->id]);
        AddonVersion::factory()->create(['addon_id' => $hiddenAddon->id]);

        // Visit the mod with versions - should see its addon
        $response = $this->actingAs($this->user)->get(route('mod.show', [$modWithVersions->id, $modWithVersions->slug]));
        $response->assertSuccessful();
        $response->assertSee($visibleAddon->name);

        // Visit the mod without versions as owner - should not see addon in public listing
        $modOwner = User::factory()->create();
        $modWithoutVersions->update(['owner_id' => $modOwner->id]);
        $hiddenAddon->update(['owner_id' => $modOwner->id]);

        $response = $this->actingAs($modOwner)->get(route('mod.show', [$modWithoutVersions->id, $modWithoutVersions->slug]));
        $response->assertSuccessful();
        // The addon should not be visible in the public listing even to the owner
        // because the parent mod has no published versions
    });
});
