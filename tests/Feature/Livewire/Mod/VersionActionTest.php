<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Enums\VerificationTrigger;
use App\Jobs\RunVerificationJob;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/**
 * Build the mount parameters for the version action component.
 *
 * @return array<string, mixed>
 */
function versionActionParams(ModVersion $version): array
{
    return [
        'versionId' => $version->id,
        'modId' => $version->mod_id,
        'versionNumber' => $version->version,
        'versionDisabled' => (bool) $version->disabled,
        'versionPublished' => (bool) $version->published_at,
    ];
}

describe('submit verification menu item', function (): void {
    it('shows the submit item to the mod owner for an eligible unverified version', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '>=4.0.0']);

        Livewire::actingAs($owner)
            ->test('mod.version-action', versionActionParams($version))
            ->call('loadMenu')
            ->assertSee('Submit for Verification');
    });

    it('shows a resubmit label when the version has a verification status', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '>=4.0.0',
            'verification_status' => VerificationStatus::Passed,
        ]);

        Livewire::actingAs($owner)
            ->test('mod.version-action', versionActionParams($version))
            ->call('loadMenu')
            ->assertSee('Resubmit Verification');
    });

    it('hides the item for versions below the minimum SPT version', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '~3.9.0']);

        Livewire::actingAs($owner)
            ->test('mod.version-action', versionActionParams($version))
            ->call('loadMenu')
            ->assertDontSee('Submit for Verification')
            ->assertDontSee('Resubmit Verification');
    });
});

describe('submit verification action', function (): void {
    it('queues a manual verification for the mod owner and notifies status badges', function (): void {
        Queue::fake();
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '>=4.0.0']);

        Livewire::actingAs($owner)
            ->test('mod.version-action', versionActionParams($version))
            ->call('submitVerification')
            ->assertDispatched('verification-submitted.mod-version-'.$version->id);

        Queue::assertPushed(RunVerificationJob::class);

        expect(VerificationResult::query()
            ->where('verifiable_type', ModVersion::class)
            ->where('verifiable_id', $version->id)
            ->where('trigger', VerificationTrigger::Manual)
            ->exists())->toBeTrue();
    });

    it('does not create a duplicate run when one is already queued', function (): void {
        Queue::fake();
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '>=4.0.0']);
        VerificationResult::factory()->forModVersion($version)->create();

        Livewire::actingAs($owner)
            ->test('mod.version-action', versionActionParams($version))
            ->call('submitVerification')
            ->assertDispatched('verification-submitted.mod-version-'.$version->id);

        expect(VerificationResult::query()->count())->toBe(1);
    });

    it('does not queue a run when the owner is rate limited', function (): void {
        Queue::fake();
        config()->set('verification.manual.max_attempts', 1);
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '>=4.0.0']);

        RateLimiter::hit('verification-submit:'.$owner->id, 3600);

        Livewire::actingAs($owner)
            ->test('mod.version-action', versionActionParams($version))
            ->call('submitVerification');

        expect(VerificationResult::query()->count())->toBe(0);
    });

    it('allows staff to queue a run past the rate limit', function (): void {
        Queue::fake();
        config()->set('verification.manual.max_attempts', 1);
        $moderator = User::factory()->moderator()->create();
        $version = ModVersion::factory()->create(['spt_version_constraint' => '>=4.0.0']);

        RateLimiter::hit('verification-submit:'.$moderator->id, 3600);

        Livewire::actingAs($moderator)
            ->test('mod.version-action', versionActionParams($version))
            ->call('submitVerification');

        expect(VerificationResult::query()->count())->toBe(1);
    });

    it('forbids users who are not authors of the mod', function (): void {
        Queue::fake();
        $version = ModVersion::factory()->create(['spt_version_constraint' => '>=4.0.0']);

        Livewire::actingAs(User::factory()->create())
            ->test('mod.version-action', versionActionParams($version))
            ->call('submitVerification')
            ->assertForbidden();

        expect(VerificationResult::query()->count())->toBe(0);
    });
});
