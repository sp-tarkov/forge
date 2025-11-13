<?php

declare(strict_types=1);

use App\Livewire\Page\Addon\Show as AddonShow;
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

describe('addon version deletion from addon detail page', function (): void {
    it('allows addon owners to delete an addon version', function (): void {
        $owner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $owner->id]);
        $version = AddonVersion::factory()->create(['addon_id' => $addon->id]);

        Livewire::actingAs($owner)
            ->test(AddonShow::class, [
                'addonId' => $addon->id,
                'slug' => $addon->slug,
            ])
            ->call('deleteAddonVersion', $version);

        expect(AddonVersion::query()->find($version->id))->toBeNull();
    });

    it('allows administrators to delete an addon version', function (): void {
        $admin = User::factory()->admin()->create();
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->create(['addon_id' => $addon->id]);

        Livewire::actingAs($admin)
            ->test(AddonShow::class, [
                'addonId' => $addon->id,
                'slug' => $addon->slug,
            ])
            ->call('deleteAddonVersion', $version);

        expect(AddonVersion::query()->find($version->id))->toBeNull();
    });

    it('prevents addon authors from deleting an addon version', function (): void {
        $author = User::factory()->create();
        $addon = Addon::factory()->create();
        $addon->additionalAuthors()->attach($author);
        $version = AddonVersion::factory()->create(['addon_id' => $addon->id]);

        Livewire::actingAs($author)
            ->test(AddonShow::class, [
                'addonId' => $addon->id,
                'slug' => $addon->slug,
            ])
            ->call('deleteAddonVersion', $version)
            ->assertForbidden();

        expect(AddonVersion::query()->find($version->id))->not->toBeNull();
    });

    it('prevents unauthorized users from deleting an addon version', function (): void {
        $user = User::factory()->create(['user_role_id' => null]);

        // Create an SPT version for mod visibility
        $sptVersion = SptVersion::factory()->create();

        $mod = Mod::factory()->create([
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
            ->for($mod, 'mod')
            ->hasVersions(1, [
                'published_at' => now(),
                'disabled' => false,
            ])
            ->create([
                'published_at' => now(),
                'disabled' => false,
            ]);
        $version = $addon->versions->first();

        Livewire::actingAs($user)
            ->test(AddonShow::class, [
                'addonId' => $addon->id,
                'slug' => $addon->slug,
            ])
            ->call('deleteAddonVersion', $version)
            ->assertForbidden();

        expect(AddonVersion::query()->find($version->id))->not->toBeNull();
    });

    it('prevents moderators from deleting an addon version', function (): void {
        $moderator = User::factory()->moderator()->create();
        $addon = Addon::factory()->create();
        $version = AddonVersion::factory()->create(['addon_id' => $addon->id]);

        Livewire::actingAs($moderator)
            ->test(AddonShow::class, [
                'addonId' => $addon->id,
                'slug' => $addon->slug,
            ])
            ->call('deleteAddonVersion', $version)
            ->assertForbidden();

        expect(AddonVersion::query()->find($version->id))->not->toBeNull();
    });
});
