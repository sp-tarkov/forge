<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Enums\VerificationTrigger;
use App\Jobs\RunVerificationJob;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\VerificationResult;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutDefer();
});

describe('version deletion from addon detail page', function (): void {
    it('allows addon owners to delete an addon version', function (): void {
        $owner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $owner->id]);
        $version = AddonVersion::factory()->create(['addon_id' => $addon->id]);

        Livewire::actingAs($owner)
            ->test('pages::addon.show', [
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
            ->test('pages::addon.show', [
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
            ->test('pages::addon.show', [
                'addonId' => $addon->id,
                'slug' => $addon->slug,
            ])
            ->call('deleteAddonVersion', $version)
            ->assertForbidden();

        expect(AddonVersion::query()->find($version->id))->not->toBeNull();
    });

    it('prevents unauthorized users from deleting an addon version', function (): void {
        $user = User::factory()->create(['user_role_id' => null]);

        $sptVersion = SptVersion::factory()->create();

        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now(),
        ]);

        // Create a mod version with SPT support (required for mod visibility).
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
            ->test('pages::addon.show', [
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
            ->test('pages::addon.show', [
                'addonId' => $addon->id,
                'slug' => $addon->slug,
            ])
            ->call('deleteAddonVersion', $version)
            ->assertForbidden();

        expect(AddonVersion::query()->find($version->id))->not->toBeNull();
    });
});

describe('submit verification', function (): void {
    /**
     * Build the mount parameters for the addon version action component.
     *
     * @return array<string, mixed>
     */
    function addonVersionActionParams(AddonVersion $version): array
    {
        return [
            'versionId' => $version->id,
            'addonId' => $version->addon_id,
            'versionNumber' => $version->version,
            'versionDisabled' => (bool) $version->disabled,
            'versionPublished' => (bool) $version->published_at,
        ];
    }

    it('shows the submit item to the addon owner', function (): void {
        $owner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $owner->id]);
        $version = AddonVersion::factory()->recycle($addon)->create();

        Livewire::actingAs($owner)
            ->test('addon.version-action', addonVersionActionParams($version))
            ->call('loadMenu')
            ->assertSee('Submit for Verification');
    });

    it('shows a resubmit label when the version has a verification status', function (): void {
        $owner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $owner->id]);
        $version = AddonVersion::factory()->recycle($addon)->create([
            'verification_status' => VerificationStatus::Passed,
        ]);

        Livewire::actingAs($owner)
            ->test('addon.version-action', addonVersionActionParams($version))
            ->call('loadMenu')
            ->assertSee('Resubmit Verification');
    });

    it('queues a manual verification for the addon owner and notifies status badges', function (): void {
        Queue::fake();
        $owner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $owner->id]);
        $version = AddonVersion::factory()->recycle($addon)->create();

        Livewire::actingAs($owner)
            ->test('addon.version-action', addonVersionActionParams($version))
            ->call('submitVerification')
            ->assertDispatched('verification-submitted.addon-version-'.$version->id);

        Queue::assertPushed(RunVerificationJob::class);

        expect(VerificationResult::query()
            ->where('verifiable_type', AddonVersion::class)
            ->where('verifiable_id', $version->id)
            ->where('trigger', VerificationTrigger::Manual)
            ->exists())->toBeTrue();
    });

    it('forbids users who are not authors of the addon', function (): void {
        Queue::fake();
        $version = AddonVersion::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('addon.version-action', addonVersionActionParams($version))
            ->call('submitVerification')
            ->assertForbidden();

        expect(VerificationResult::query()->count())->toBe(0);
    });
});
