<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('Mod Addon Toggle', function (): void {
    it('allows mod owner to disable addons for their mod', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($owner, 'owner')->create(['addons_disabled' => false]);

        expect($mod->addons_enabled)->toBeTrue();

        $mod->addons_disabled = true;
        $mod->save();

        expect($mod->addons_enabled)->toBeFalse();
    });

    it('prevents creating addons when mod has addons disabled', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($owner, 'owner')->create(['addons_disabled' => true]);

        $this->actingAs($owner);

        // Attempt to create an addon for a mod with addons disabled
        $response = $this->get(route('addon.guidelines', ['mod' => $mod->id]));

        // Should be denied
        $response->assertForbidden();
    });

    it('shows addons tab when mod has addons enabled', function (): void {
        $owner = User::factory()->withMfa()->create();

        // Create an SPT version for compatibility
        $sptVersion = SptVersion::factory()->create([
            'version' => '3.10.0',
            'version_major' => 3,
            'version_minor' => 10,
            'version_patch' => 0,
            'mod_count' => 5,
        ]);

        $mod = Mod::factory()->for($owner, 'owner')->create([
            'addons_disabled' => false,
            'published_at' => now(),
        ]);

        // Create a compatible version so the mod is publicly visible
        $version = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '3.10.0',
        ]);
        $version->sptVersions()->sync($sptVersion->id);

        // Create some addons for the mod
        Addon::factory()
            ->for($mod)
            ->for($owner, 'owner')
            ->published()
            ->count(3)
            ->create();

        Livewire::withoutLazyLoading()
            ->test('pages::mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertSee('Addons')
            ->assertSee('3 Addons');
    });

    it('hides addon creation when mod has addons disabled', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($owner, 'owner')->create([
            'addons_disabled' => true,
            'published_at' => now(),
        ]);

        $component = Livewire::withoutLazyLoading()
            ->test('pages::mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ]);

        $html = $component->html();
        expect($html)->not->toContain("selectedTab = 'addons'");
        expect($html)->not->toContain('<option value="addons">');

        $component->assertDontSee('Create Addon')
            ->assertDontSee('Create First Addon');
    });

    it('shows create addon button for authorized users when addons enabled', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($owner, 'owner')->create([
            'addons_disabled' => false,
            'published_at' => now(),
        ]);

        $this->actingAs($owner);

        Livewire::withoutLazyLoading()
            ->test('pages::mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertSee('Addons')
            ->assertSee('Create First Addon');
    });

    it('existing addons remain accessible when addons are disabled', function (): void {
        $owner = User::factory()->withMfa()->create();

        // Create SPT version for mod visibility
        $sptVersion = SptVersion::factory()->create();

        $mod = Mod::factory()->for($owner, 'owner')->create([
            'addons_disabled' => false,
            'disabled' => false,
            'published_at' => now(),
        ]);

        // Create a mod version with SPT support (required for mod visibility)
        $modVersion = ModVersion::factory()->for($mod)->create([
            'disabled' => false,
            'published_at' => now(),
        ]);
        $modVersion->sptVersions()->sync($sptVersion);

        $addon = Addon::factory()
            ->for($mod)
            ->for($owner, 'owner')
            ->published()
            ->withVersions(1)
            ->create();

        // Ensure addon has a published version so it's publicly visible
        $addon->versions()->first()?->update([
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        // Disable addons
        $mod->addons_disabled = true;
        $mod->save();

        // Existing addon should still be accessible
        $response = $this->get(route('addon.show', [$addon->id, $addon->slug]));
        $response->assertOk();
        $response->assertSee($addon->name);
    });

    it('counts attached addons correctly', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($owner, 'owner')->create([
            'addons_disabled' => false,
            'published_at' => now(),
        ]);

        // Create regular addons
        Addon::factory()
            ->for($mod)
            ->for($owner, 'owner')
            ->published()
            ->count(2)
            ->create();

        // Create a detached addon
        Addon::factory()
            ->for($mod)
            ->for($owner, 'owner')
            ->published()
            ->create([
                'detached_at' => now(),
                'detached_by_user_id' => $owner->id,
            ]);

        // attachedAddons should only count non-detached addons
        expect($mod->attachedAddons()->count())->toBe(2);
        expect($mod->addons()->count())->toBe(3);
    });

    it('allows any user with MFA to create addons when enabled', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create([
            'addons_disabled' => false,
            'published_at' => now(),
        ]);

        $this->actingAs($user);

        // Any user with MFA should be able to access addon guidelines page
        $response = $this->get(route('addon.guidelines', ['mod' => $mod->id]));
        $response->assertOk();
    });

    it('prevents users without MFA from creating addons', function (): void {
        $userWithoutMfa = User::factory()->create();
        $mod = Mod::factory()->create([
            'addons_disabled' => false,
            'published_at' => now(),
        ]);

        $this->actingAs($userWithoutMfa);

        // User without MFA should be denied
        $response = $this->get(route('addon.guidelines', ['mod' => $mod->id]));
        $response->assertForbidden();
    });
});
