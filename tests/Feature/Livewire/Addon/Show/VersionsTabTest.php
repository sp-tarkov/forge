<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Dependency;
use App\Models\DependencyResolved;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;
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

describe('verification', function (): void {
    it('shows a passed verification badge to guests when the latest verification passed', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($owner, 'owner')->create(['published_at' => now()]);
        $addon = Addon::factory()->for($mod)->for($owner, 'owner')->published()->create();
        $version = AddonVersion::factory()->for($addon)->create([
            'verification_status' => VerificationStatus::Passed,
        ]);
        VerificationResult::factory()->forAddonVersion($version)->passed()->create();

        Livewire::withoutLazyLoading()
            ->test('addon.show.versions-tab', ['addonId' => $addon->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Passed')
            ->assertSuccessful();
    });

    it('shows a failed verification badge to guests and the addon owner', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($owner, 'owner')->create(['published_at' => now()]);
        $addon = Addon::factory()->for($mod)->for($owner, 'owner')->published()->create();
        AddonVersion::factory()->for($addon)->create([
            'verification_status' => VerificationStatus::Failed,
        ]);

        Livewire::withoutLazyLoading()
            ->test('addon.show.versions-tab', ['addonId' => $addon->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Failed Verification')
            ->assertSuccessful();

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('addon.show.versions-tab', ['addonId' => $addon->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Failed Verification')
            ->assertSuccessful();
    });

    it('shows the live status badge to the addon owner but not to guests', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($owner, 'owner')->create(['published_at' => now()]);
        $addon = Addon::factory()->for($mod)->for($owner, 'owner')->published()->create();
        AddonVersion::factory()->for($addon)->create();

        Livewire::withoutLazyLoading()
            ->test('addon.show.versions-tab', ['addonId' => $addon->id])
            ->assertDontSeeHtml('data-test="verification-status-shield"')
            ->assertSuccessful();

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('addon.show.versions-tab', ['addonId' => $addon->id])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Unverified')
            ->assertSuccessful();
    });
});
