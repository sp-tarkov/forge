<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;
use Livewire\Livewire;

describe('verification status badge', function (): void {
    it('shows an unverified badge to the mod owner when no results exist', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '>=4.0.0']);

        Livewire::actingAs($owner)
            ->test('verification-status', [
                'verifiableId' => $version->id,
                'verifiableType' => ModVersion::class,
                'modalName' => 'version-verification-'.$version->id,
            ])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Unverified')
            ->assertSuccessful();
    });

    it('shows the latest result status to the mod owner', function (string $state, string $expected): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create();
        VerificationResult::factory()->forModVersion($version)->{$state}()->create();

        Livewire::actingAs($owner)
            ->test('verification-status', [
                'verifiableId' => $version->id,
                'verifiableType' => ModVersion::class,
                'modalName' => 'version-verification-'.$version->id,
            ])
            ->assertSee($expected)
            ->assertSuccessful();
    })->with([
        'running' => ['running', 'Running'],
        'passed' => ['passed', 'Passed'],
        'failed' => ['failed', 'Failed'],
        'errored' => ['errored', 'Error'],
    ]);

    it('shows a pending badge for a queued run', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create();
        VerificationResult::factory()->forModVersion($version)->create();

        Livewire::actingAs($owner)
            ->test('verification-status', [
                'verifiableId' => $version->id,
                'verifiableType' => ModVersion::class,
                'modalName' => 'version-verification-'.$version->id,
            ])
            ->assertSee('Pending')
            ->assertSuccessful();
    });

    it('shows the latest result status to guests', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->passed()->create();

        Livewire::test('verification-status', [
            'verifiableId' => $version->id,
            'verifiableType' => ModVersion::class,
            'modalName' => 'version-verification-'.$version->id,
        ])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Passed')
            ->assertSuccessful();
    });

    it('shows the active run to guests and polls until it completes', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->create();

        Livewire::test('verification-status', [
            'verifiableId' => $version->id,
            'verifiableType' => ModVersion::class,
            'modalName' => 'version-verification-'.$version->id,
        ])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Pending')
            ->assertSeeHtml('wire:poll.10s')
            ->assertSuccessful();
    });

    it('renders nothing for guests and regular users when the version is unverified', function (): void {
        $version = ModVersion::factory()->create();

        Livewire::test('verification-status', [
            'verifiableId' => $version->id,
            'verifiableType' => ModVersion::class,
            'modalName' => 'version-verification-'.$version->id,
        ])
            ->assertDontSeeHtml('data-test="verification-status-shield"')
            ->assertSuccessful();

        Livewire::actingAs(User::factory()->create())
            ->test('verification-status', [
                'verifiableId' => $version->id,
                'verifiableType' => ModVersion::class,
                'modalName' => 'version-verification-'.$version->id,
            ])
            ->assertDontSeeHtml('data-test="verification-status-shield"')
            ->assertSuccessful();
    });

    it('renders nothing for the owner of an unverified version below the minimum SPT version', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '~3.9.0']);

        Livewire::actingAs($owner)
            ->test('verification-status', [
                'verifiableId' => $version->id,
                'verifiableType' => ModVersion::class,
                'modalName' => 'version-verification-'.$version->id,
            ])
            ->assertDontSeeHtml('data-test="verification-status-shield"')
            ->assertSuccessful();
    });

    it('shows a passed badge to guests when the version passed verification', function (): void {
        $version = ModVersion::factory()->create(['verification_status' => VerificationStatus::Passed]);

        Livewire::test('verification-status', [
            'verifiableId' => $version->id,
            'verifiableType' => ModVersion::class,
            'modalName' => 'version-verification-'.$version->id,
        ])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Passed')
            ->assertDontSeeHtml('wire:poll.10s')
            ->assertSuccessful();
    });

    it('shows a failed version status to guests and regular users', function (): void {
        $version = ModVersion::factory()->create(['verification_status' => VerificationStatus::Failed]);

        Livewire::test('verification-status', [
            'verifiableId' => $version->id,
            'verifiableType' => ModVersion::class,
            'modalName' => 'version-verification-'.$version->id,
        ])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Failed');

        Livewire::actingAs(User::factory()->create())
            ->test('verification-status', [
                'verifiableId' => $version->id,
                'verifiableType' => ModVersion::class,
                'modalName' => 'version-verification-'.$version->id,
            ])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Failed');
    });

    it('falls back to the denormalized status for the owner when no results exist', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['verification_status' => VerificationStatus::Failed]);

        Livewire::actingAs($owner)
            ->test('verification-status', [
                'verifiableId' => $version->id,
                'verifiableType' => ModVersion::class,
                'modalName' => 'version-verification-'.$version->id,
            ])
            ->assertSee('Failed')
            ->assertSuccessful();
    });

    it('polls while the latest run is active and stops at terminal states', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create();
        $result = VerificationResult::factory()->forModVersion($version)->create();

        Livewire::actingAs($owner)
            ->test('verification-status', [
                'verifiableId' => $version->id,
                'verifiableType' => ModVersion::class,
                'modalName' => 'version-verification-'.$version->id,
            ])
            ->assertSeeHtml('wire:poll.10s');

        $result->delete();
        VerificationResult::factory()->forModVersion($version)->passed()->create();

        Livewire::actingAs($owner)
            ->test('verification-status', [
                'verifiableId' => $version->id,
                'verifiableType' => ModVersion::class,
                'modalName' => 'version-verification-'.$version->id,
            ])
            ->assertDontSeeHtml('wire:poll.10s');
    });

    it('refreshes the badge when a verification is submitted for the version', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '>=4.0.0']);

        $component = Livewire::actingAs($owner)
            ->test('verification-status', [
                'verifiableId' => $version->id,
                'verifiableType' => ModVersion::class,
                'modalName' => 'version-verification-'.$version->id,
            ])
            ->assertSee('Unverified');

        VerificationResult::factory()->forModVersion($version)->create();

        $component->dispatch('verification-submitted.mod-version-'.$version->id)
            ->assertSee('Pending')
            ->assertSeeHtml('wire:poll.10s');
    });

    it('shows the badge to the addon owner and refreshes on the addon event', function (): void {
        $owner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $owner->id]);
        $version = AddonVersion::factory()->recycle($addon)->create();

        $component = Livewire::actingAs($owner)
            ->test('verification-status', [
                'verifiableId' => $version->id,
                'verifiableType' => AddonVersion::class,
                'modalName' => 'addon-version-verification-'.$version->id,
            ])
            ->assertSeeHtml('data-test="verification-status-shield"')
            ->assertSee('Unverified');

        VerificationResult::factory()->forAddonVersion($version)->create();

        $component->dispatch('verification-submitted.addon-version-'.$version->id)
            ->assertSee('Pending');
    });

    it('rejects unsupported verifiable types', function (): void {
        $user = User::factory()->create();

        Livewire::test('verification-status', [
            'verifiableId' => $user->id,
            'verifiableType' => User::class,
            'modalName' => 'bad-type',
        ])->assertNotFound();
    });
});
