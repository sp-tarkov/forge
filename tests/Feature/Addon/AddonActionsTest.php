<?php

declare(strict_types=1);

use App\Livewire\Addon\Action;
use App\Livewire\Page\Addon\Show as AddonShow;
use App\Livewire\Page\Mod\Show as ModShow;
use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('action component mounting', function (): void {
    it('mounts the component with the provided addon', function (): void {
        // Create minimal addon with recycled resources to reduce factory overhead
        $user = User::factory()->create();
        $license = License::factory()->create();
        $mod = Mod::withoutEvents(fn () => Mod::factory()->recycle([$user, $license])->create());

        // Use withoutEvents to skip factory's afterCreating callback (SourceCodeLinks)
        $addon = Addon::withoutEvents(fn () => Addon::factory()
            ->recycle([$mod, $user, $license])
            ->create());

        Livewire::test(Action::class, [
            'addonId' => $addon->id,
            'addonName' => $addon->name,
            'addonDisabled' => (bool) $addon->disabled,
            'addonPublished' => (bool) $addon->published_at && $addon->published_at <= now(),
            'addonDetached' => false,
        ])
            ->assertSet('addon.id', $addon->id);
    });
});

describe('action component visibility', function (): void {
    it('displays on addon detail pages for addon owners', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->get(route('addon.show', [$addon->id, $addon->slug]))
            ->assertSeeLivewire(Action::class);
    });

    it('displays on addon detail pages for administrators', function (): void {
        $user = User::factory()->admin()->create();

        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        $this->actingAs($user)
            ->get(route('addon.show', [$addon->id, $addon->slug]))
            ->assertSeeLivewire(Action::class);
    });

    it('does not display on addon detail pages for normal users', function (): void {
        $owner = User::factory()->create();
        $user = User::factory()->create(['user_role_id' => null]);

        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'owner_id' => $owner->id]);

        $this->actingAs($user)
            ->get(route('addon.show', [$addon->id, $addon->slug]))
            ->assertDontSeeLivewire(Action::class);
    });
});

describe('addon deletion from addon detail page', function (): void {
    it('allows administrators to delete an addon from the addon detail page', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(AddonShow::class, [
                'addonId' => $addon->id,
                'slug' => $addon->slug,
            ])
            ->call('deleteAddon', $addon, 'addon.show');

        expect(Addon::query()->find($addon->id))->toBeNull();
    });

    it('prevents normal users from deleting an addon from the addon detail page', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()
            ->hasVersions(1, [
                'published_at' => now(),
                'disabled' => false,
            ])
            ->create([
                'mod_id' => $mod->id,
                'published_at' => now(),
                'disabled' => false,
            ]);

        $user = User::factory()->create(['user_role_id' => null]);

        Livewire::actingAs($user)
            ->test(AddonShow::class, [
                'addonId' => $addon->id,
                'slug' => $addon->slug,
            ])
            ->call('deleteAddon', $addon, 'addon.show')
            ->assertForbidden();
    });
});

describe('addon deletion from mod show page', function (): void {
    it('allows administrators to delete an addon from the mod show page', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ModShow::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->call('deleteAddon', $addon);

        expect(Addon::query()->find($addon->id))->toBeNull();
    });

    it('prevents normal users from deleting an addon from the mod show page', function (): void {
        $user = User::factory()->create(['user_role_id' => null]);

        // Create an SPT version for compatibility
        $sptVersion = SptVersion::factory()->create([
            'version' => '3.10.0',
            'version_major' => 3,
            'version_minor' => 10,
            'version_patch' => 0,
            'mod_count' => 5,
        ]);

        $mod = Mod::factory()->create([
            'owner_id' => User::factory()->create()->id,
            'published_at' => now(),
            'disabled' => false,
        ]);

        // Create a compatible version so the mod is publicly visible
        $version = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '3.10.0',
        ]);
        $version->sptVersions()->sync($sptVersion->id);

        $addon = Addon::factory()->create(['mod_id' => $mod->id]);

        Livewire::actingAs($user)
            ->test(ModShow::class, [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->call('deleteAddon', $addon)
            ->assertForbidden();
    });
});

describe('addon publishing functionality', function (): void {
    it('allows addon owners to publish an addon with specified date', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'owner_id' => $owner->id, 'published_at' => null]);

        $publishDate = Date::now()->addHour()->format('Y-m-d\TH:i');

        Livewire::actingAs($owner)
            ->test(Action::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => (bool) $addon->disabled,
                'addonPublished' => false,
                'addonDetached' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish')
            ->assertSet('addonPublished', true);

        $addon->refresh();
        expect($addon->published_at)->not->toBeNull();
        expect($addon->published_at->format('Y-m-d H:i:s'))->toBe(Date::parse($publishDate)->format('Y-m-d H:i:s'));
    });

    it('allows addon owners to unpublish an addon', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'owner_id' => $owner->id, 'published_at' => Date::now()]);

        Livewire::actingAs($owner)
            ->test(Action::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => (bool) $addon->disabled,
                'addonPublished' => true,
                'addonDetached' => false,
            ])
            ->call('unpublish')
            ->assertSet('addonPublished', false);

        $addon->refresh();
        expect($addon->published_at)->toBeNull();
    });

    it('prevents unauthorized users from publishing/unpublishing addons', function (): void {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'owner_id' => $owner->id]);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        // Test unauthorized publish
        Livewire::actingAs($otherUser)
            ->test(Action::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => (bool) $addon->disabled,
                'addonPublished' => false,
                'addonDetached' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish')
            ->assertForbidden();

        // Test unauthorized unpublish
        Livewire::actingAs($otherUser)
            ->test(Action::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => (bool) $addon->disabled,
                'addonPublished' => true,
                'addonDetached' => false,
            ])
            ->call('unpublish')
            ->assertForbidden();
    });

    it('allows addon authors to publish/unpublish addons', function (): void {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'owner_id' => $owner->id, 'published_at' => null]);
        $addon->authors()->attach($author);

        $publishDate = Date::now()->format('Y-m-d\TH:i');

        // Test author can publish
        Livewire::actingAs($author)
            ->test(Action::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => (bool) $addon->disabled,
                'addonPublished' => false,
                'addonDetached' => false,
            ])
            ->set('publishedAt', $publishDate)
            ->call('publish')
            ->assertSet('addonPublished', true);

        $addon->refresh();
        expect($addon->published_at)->not->toBeNull();

        // Test author can unpublish
        Livewire::actingAs($author)
            ->test(Action::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => (bool) $addon->disabled,
                'addonPublished' => true,
                'addonDetached' => false,
            ])
            ->call('unpublish')
            ->assertSet('addonPublished', false);

        $addon->refresh();
        expect($addon->published_at)->toBeNull();
    });
});

describe('addon enable/disable functionality', function (): void {
    it('allows administrators to disable addons', function (): void {
        $user = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'disabled' => false]);

        Livewire::actingAs($user)
            ->test(Action::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => false,
                'addonPublished' => (bool) $addon->published_at,
                'addonDetached' => false,
            ])
            ->call('disable')
            ->assertSet('addonDisabled', true);

        $addon->refresh();
        expect($addon->disabled)->toBeTrue();
    });

    it('allows administrators to enable addons', function (): void {
        $user = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'disabled' => true]);

        Livewire::actingAs($user)
            ->test(Action::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => true,
                'addonPublished' => (bool) $addon->published_at,
                'addonDetached' => false,
            ])
            ->call('enable')
            ->assertSet('addonDisabled', false);

        $addon->refresh();
        expect($addon->disabled)->toBeFalse();
    });

    it('prevents normal users from disabling/enabling addons', function (): void {
        $user = User::factory()->create(['user_role_id' => null]);
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'disabled' => false]);

        Livewire::actingAs($user)
            ->test(Action::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => false,
                'addonPublished' => (bool) $addon->published_at,
                'addonDetached' => false,
            ])
            ->call('disable')
            ->assertForbidden();
    });

    it('allows moderators to disable/enable addons', function (): void {
        $user = User::factory()->moderator()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'disabled' => false]);

        // Test moderator can disable
        Livewire::actingAs($user)
            ->test(Action::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => false,
                'addonPublished' => (bool) $addon->published_at,
                'addonDetached' => false,
            ])
            ->call('disable')
            ->assertSet('addonDisabled', true);

        $addon->refresh();
        expect($addon->disabled)->toBeTrue();

        // Test moderator can enable
        Livewire::actingAs($user)
            ->test(Action::class, [
                'addonId' => $addon->id,
                'addonName' => $addon->name,
                'addonDisabled' => true,
                'addonPublished' => (bool) $addon->published_at,
                'addonDetached' => false,
            ])
            ->call('enable')
            ->assertSet('addonDisabled', false);

        $addon->refresh();
        expect($addon->disabled)->toBeFalse();
    });
});
