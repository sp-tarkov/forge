<?php

declare(strict_types=1);

use App\Livewire\Page\Mod\Show;
use App\Models\Addon;
use App\Models\AddonVersion;
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

describe('Mod Version View Addons Link', function (): void {
    it('shows view addons link when mod version has compatible addons', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'addons_disabled' => false]);

        $sptVersion = SptVersion::factory()->create();
        $modVersion = ModVersion::factory()
            ->for($mod)
            ->create(['version' => '1.0.0', 'published_at' => now()]);

        $modVersion->sptVersions()->sync([$sptVersion->id]);

        // Create an addon for this mod with a compatible version
        $addon = Addon::factory()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['published_at' => now()]);

        AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0', 'published_at' => now()]);

        Livewire::withoutLazyLoading()
            ->test(Show::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertSee('View Addons');
    });

    it('hides view addons link when mod cannot have addons', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'addons_disabled' => true]);

        $sptVersion = SptVersion::factory()->create();
        $modVersion = ModVersion::factory()
            ->for($mod)
            ->create(['version' => '1.0.0', 'published_at' => now()]);

        $modVersion->sptVersions()->sync([$sptVersion->id]);

        // Even if an addon exists (shouldn't happen but let's test)
        $addon = Addon::factory()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['published_at' => now()]);

        AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0', 'published_at' => now()]);

        Livewire::withoutLazyLoading()
            ->test(Show::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertDontSee('View Addons');
    });

    it('hides view addons link when only unpublished addons are compatible', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'addons_disabled' => false]);

        $sptVersion = SptVersion::factory()->create();
        $modVersion = ModVersion::factory()
            ->for($mod)
            ->create(['version' => '1.0.0', 'published_at' => now()]);

        $modVersion->sptVersions()->sync([$sptVersion->id]);

        // Create an unpublished addon
        $addon = Addon::factory()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['published_at' => null]);

        AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0', 'published_at' => now()]);

        Livewire::withoutLazyLoading()
            ->test(Show::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertDontSee('View Addons');
    });

    it('hides view addons link when only disabled addons are compatible', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'addons_disabled' => false]);

        $sptVersion = SptVersion::factory()->create();
        $modVersion = ModVersion::factory()
            ->for($mod)
            ->create(['version' => '1.0.0', 'published_at' => now()]);

        $modVersion->sptVersions()->sync([$sptVersion->id]);

        // Create a disabled addon
        $addon = Addon::factory()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'disabled' => true]);

        AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0', 'published_at' => now()]);

        Livewire::withoutLazyLoading()
            ->test(Show::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertDontSee('View Addons');
    });

    it('hides view addons link when only detached addons are compatible', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'addons_disabled' => false]);

        $sptVersion = SptVersion::factory()->create();
        $modVersion = ModVersion::factory()
            ->for($mod)
            ->create(['version' => '1.0.0', 'published_at' => now()]);

        $modVersion->sptVersions()->sync([$sptVersion->id]);

        // Create a detached addon
        $addon = Addon::factory()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'detached_at' => now()]);

        AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0', 'published_at' => now()]);

        Livewire::withoutLazyLoading()
            ->test(Show::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertDontSee('View Addons');
    });
});
