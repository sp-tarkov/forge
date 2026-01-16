<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutDefer();
});

it('shows addons that have any version compatible when filtering by mod version', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()
        ->for($user, 'owner')
        ->create(['published_at' => now()]);

    $modVersion = ModVersion::factory()->create([
        'mod_id' => $mod->id,
        'version' => '8.7.4',
        'published_at' => now(),
    ]);

    // Create an addon whose latest version IS compatible
    $compatibleAddon = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create(['name' => 'Compatible Addon']);

    $compatibleAddonVersion = AddonVersion::factory()->create([
        'addon_id' => $compatibleAddon->id,
        'version' => '2.0.0',
        'published_at' => now(),
        'mod_version_constraint' => '>='.$modVersion->version,
    ]);
    // Observer automatically resolves compatible mod versions

    // Create an addon whose latest version is NOT compatible (but has an older compatible version)
    // This should still be shown because it has ANY compatible version
    $hasOlderCompatibleAddon = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create(['name' => 'Has Older Compatible Version']);

    // Create an older version that IS compatible
    $olderCompatibleVersion = AddonVersion::factory()->create([
        'addon_id' => $hasOlderCompatibleAddon->id,
        'version' => '1.0.0',
        'published_at' => now()->subDays(10),
        'version_major' => 1,
        'version_minor' => 0,
        'version_patch' => 0,
        'mod_version_constraint' => '>='.$modVersion->version,
    ]);
    // Observer automatically resolves compatible mod versions

    // Create a newer version that is NOT compatible (this will be the latest by version number)
    AddonVersion::factory()->create([
        'addon_id' => $hasOlderCompatibleAddon->id,
        'version' => '2.0.0',
        'published_at' => now(),
        'version_major' => 2,
        'version_minor' => 0,
        'version_patch' => 0,
        'mod_version_constraint' => '^9.0.0', // Not compatible with 8.7.4
    ]);

    // Create an addon with no compatible versions at all
    $neverCompatibleAddon = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create(['name' => 'Never Compatible']);

    AddonVersion::factory()->create([
        'addon_id' => $neverCompatibleAddon->id,
        'version' => '1.0.0',
        'published_at' => now(),
        'mod_version_constraint' => '^10.0.0', // Not compatible with 8.7.4
    ]);

    $this->actingAs($user);

    // When filtering by the specific mod version
    Livewire::withoutLazyLoading()
        ->test('mod.show.addons-tab', ['modId' => $mod->id])
        ->set('selectedModVersionId', $modVersion->id)
        ->assertSuccessful()
        ->assertSee('Compatible Addon')
        ->assertSee('Has Older Compatible Version') // Should show because it has ANY compatible version
        ->assertDontSee('Never Compatible');
});

it('shows all addons when no mod version filter is selected', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()
        ->for($user, 'owner')
        ->create(['published_at' => now()]);

    // Create addons with various compatibility states
    $addon1 = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create(['name' => 'Has Compatible Versions']);

    $addon2 = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create(['name' => 'No Compatible Versions']);

    // Give addon1 a version with compatibility
    $modVersion = ModVersion::factory()->create([
        'mod_id' => $mod->id,
        'published_at' => now(),
    ]);

    $addonVersion1 = AddonVersion::factory()->create([
        'addon_id' => $addon1->id,
        'published_at' => now(),
        'mod_version_constraint' => '>='.$modVersion->version,
    ]);
    // Observer automatically resolves compatible mod versions

    // Give addon2 a version with no compatibility
    AddonVersion::factory()->create([
        'addon_id' => $addon2->id,
        'published_at' => now(),
        'mod_version_constraint' => '^99.0.0',
    ]);

    $this->actingAs($user);

    // When NOT filtering (All versions selected)
    Livewire::withoutLazyLoading()
        ->test('mod.show.addons-tab', ['modId' => $mod->id])
        ->assertSuccessful()
        ->assertSee('Has Compatible Versions')
        ->assertSee('No Compatible Versions');
});
