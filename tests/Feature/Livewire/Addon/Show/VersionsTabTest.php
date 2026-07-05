<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Dependency;
use App\Models\DependencyResolved;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Livewire\Livewire;

/**
 * Create a published addon whose version has a resolved dependency on an unpublished mod, returning the addon to
 * view. The resolved rows are saved quietly to mirror production data where the dependency mod was hidden after
 * resolution ran.
 */
function createAddonWithHiddenDependency(): Addon
{
    $owner = User::factory()->withMfa()->create();
    $mod = Mod::factory()->for($owner, 'owner')->create(['published_at' => now()]);
    $addon = Addon::factory()->for($mod)->for($owner, 'owner')->published()->create();
    $addonVersion = AddonVersion::factory()->for($addon)->create(['version' => '2.0.0']);

    $hiddenMod = Mod::factory()->unpublished()->create(['name' => 'Hidden Dependency Mod']);
    $hiddenModVersion = ModVersion::factory()->for($hiddenMod)->create(['version' => '1.0.0']);

    $dependency = Dependency::factory()->make([
        'dependable_type' => AddonVersion::class,
        'dependable_id' => $addonVersion->id,
        'dependent_mod_id' => $hiddenMod->id,
        'constraint' => '^1.0',
    ]);
    $dependency->saveQuietly();

    DependencyResolved::factory()->make([
        'dependable_type' => AddonVersion::class,
        'dependable_id' => $addonVersion->id,
        'dependency_id' => $dependency->id,
        'resolved_mod_version_id' => $hiddenModVersion->id,
    ])->saveQuietly();

    return $addon;
}

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('dependencies on hidden mods', function (): void {
    it('renders for guests without listing a dependency whose mod is unpublished', function (): void {
        $addon = createAddonWithHiddenDependency();

        Livewire::withoutLazyLoading()
            ->test('addon.show.versions-tab', ['addonId' => $addon->id])
            ->assertSee('Version 2.0.0')
            ->assertDontSee('Hidden Dependency Mod')
            ->assertSuccessful();
    });

    it('lists a dependency on an unpublished mod for administrators', function (): void {
        $addon = createAddonWithHiddenDependency();
        $admin = User::factory()->admin()->create();

        Livewire::withoutLazyLoading()
            ->actingAs($admin)
            ->test('addon.show.versions-tab', ['addonId' => $addon->id])
            ->assertSee('Hidden Dependency Mod')
            ->assertSuccessful();
    });
});
