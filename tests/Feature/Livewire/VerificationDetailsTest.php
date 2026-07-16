<?php

declare(strict_types=1);

use App\Enums\VerificationTrigger;
use App\Jobs\RunVerificationJob;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Number;
use Livewire\Livewire;

describe('verification details', function (): void {
    it('renders passed verification details', function (): void {
        $version = ModVersion::factory()->create();
        $result = VerificationResult::factory()->forModVersion($version)->passed()->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('File Verification')
            ->assertSee('Passed')
            ->assertSee($result->download_url)
            ->assertSee(Number::fileSize($result->downloaded_size, precision: 2))
            ->assertSee($result->downloaded_sha256)
            ->assertSee('package.json')
            ->assertSee('src')
            ->assertSee('README.md')
            ->assertSuccessful();
    });

    it('renders the per-check results', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->passed()->withChecks([
            ['name' => 'archive_extraction', 'status' => 'passed', 'report_only' => false, 'message' => null, 'data' => []],
            ['name' => 'manifest_present', 'status' => 'failed', 'report_only' => true, 'message' => 'No manifest found', 'data' => []],
        ], '4')->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Checks')
            ->assertSee('Archive Extraction')
            ->assertSee('archive_extraction')
            ->assertSee('Confirms the uploaded archive can be opened')
            ->assertSee('Manifest Present')
            ->assertSee('manifest_present')
            ->assertSee('Report only')
            ->assertSee('No manifest found')
            ->assertSuccessful();
    });

    it('shows a passed file download check when the result has no stored checks', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->passed()->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Checks')
            ->assertSee('File Download')
            ->assertSee('file_download')
            ->assertSuccessful();
    });

    it('shows the file download check first among the stored checks', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->passed()->withChecks()->create(['download_ok' => true]);

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSeeInOrder(['file_download', 'archive_extraction'])
            ->assertSuccessful();
    });

    it('shows the latest failed result to guests even when an older run passed', function (): void {
        $version = ModVersion::factory()->create();
        $passed = VerificationResult::factory()->forModVersion($version)->passed()->create();
        VerificationResult::factory()->forModVersion($version)->failed('Internal failure diagnostics')->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Failed')
            ->assertSee('Internal failure diagnostics')
            ->assertDontSee($passed->downloaded_sha256)
            ->assertSuccessful();
    });

    it('shows the pending run to guests when a newer pending run exists', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->passed()->create();
        VerificationResult::factory()->forModVersion($version)->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Pending')
            ->assertSee('This panel updates automatically.')
            ->assertSuccessful();
    });

    it('shows a fallback message when no results exist', function (): void {
        $version = ModVersion::factory()->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Verification details are currently unavailable.')
            ->assertSuccessful();
    });

    it('shows the failed result to regular users', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->failed('Download returned HTTP 404')->create();

        $this->actingAs(User::factory()->create());

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Failed')
            ->assertSee('Download returned HTTP 404')
            ->assertSuccessful();
    });

    it('shows the failed result to the mod owner as a synthesized download check', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create();
        VerificationResult::factory()->forModVersion($version)->failed('Download returned HTTP 404')->create();

        $this->actingAs($owner);

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Failed')
            ->assertSee('Checks')
            ->assertSee('File Download')
            ->assertSee('file_download')
            ->assertSee('Confirms the download URL serves the mod archive file directly.')
            ->assertSee('Download returned HTTP 404')
            ->assertSuccessful();
    });

    it('synthesizes an archive extraction check when the run failed after downloading', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create();
        VerificationResult::factory()->forModVersion($version)
            ->failed('Downloaded file is not a ZIP or 7z archive')
            ->create(['download_ok' => true, 'archive_ok' => false]);

        $this->actingAs($owner);

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Downloaded file is not a ZIP or 7z archive')
            ->assertSeeInOrder(['file_download', 'archive_extraction'])
            ->assertSuccessful();
    });

    it('renders the stored checks for a failed run behind the file download check', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create();
        VerificationResult::factory()->forModVersion($version)
            ->failed('mod_structure: No mod files found')
            ->withChecks([
                ['name' => 'archive_extraction', 'status' => 'passed', 'report_only' => false, 'message' => null, 'data' => []],
                ['name' => 'mod_structure', 'status' => 'failed', 'report_only' => false, 'message' => 'No mod files found', 'data' => []],
            ])
            ->create(['download_ok' => true]);

        $this->actingAs($owner);

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('No mod files found')
            ->assertSeeInOrder(['file_download', 'mod_structure', 'archive_extraction'])
            ->assertSuccessful();
    });

    it('shows the failed result to an additional author', function (): void {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($author);
        $version = ModVersion::factory()->recycle($mod)->create();
        VerificationResult::factory()->forModVersion($version)->failed()->create();

        $this->actingAs($author);

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Failed')
            ->assertSuccessful();
    });

    it('shows the failed result to moderators', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->failed()->create();

        $this->actingAs(User::factory()->moderator()->create());

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Failed')
            ->assertSuccessful();
    });

    it('shows the latest failed result to the mod owner even when an older passed result exists', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create();
        $passed = VerificationResult::factory()->forModVersion($version)->passed()->create();
        VerificationResult::factory()->forModVersion($version)->failed('Download returned HTTP 404')->create();

        $this->actingAs($owner);

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Failed')
            ->assertSee('Download returned HTTP 404')
            ->assertDontSee($passed->downloaded_sha256)
            ->assertSuccessful();
    });

    it('shows a message when the file tree is empty', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->passed()->create(['file_tree' => []]);

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('No file listing available.')
            ->assertSuccessful();
    });

    it('rejects unsupported verifiable types', function (): void {
        $user = User::factory()->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $user->id, 'verifiableType' => User::class])
            ->assertNotFound();
    });

    it('displays a placeholder while lazy loading', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->passed()->create();

        $component = Livewire::test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->instance();
        $placeholder = $component->placeholder();

        expect($placeholder->render())->toContain('data-flux-skeleton');
    });
});

describe('addon verification details', function (): void {
    it('renders passed verification details for an addon version', function (): void {
        $version = AddonVersion::factory()->create();
        $result = VerificationResult::factory()->forAddonVersion($version)->passed()->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => AddonVersion::class])
            ->assertSee('File Verification')
            ->assertSee('Passed')
            ->assertSee($result->download_url)
            ->assertSuccessful();
    });

    it('shows the failed result to the addon owner', function (): void {
        $owner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $owner->id]);
        $version = AddonVersion::factory()->recycle($addon)->create();
        VerificationResult::factory()->forAddonVersion($version)->failed('Download returned HTTP 404')->create();

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => AddonVersion::class])
            ->assertSee('Failed')
            ->assertSee('Download returned HTTP 404')
            ->assertSuccessful();
    });

    it('shows the failed result to regular users', function (): void {
        $version = AddonVersion::factory()->create();
        VerificationResult::factory()->forAddonVersion($version)->failed('Download returned HTTP 404')->create();

        Livewire::actingAs(User::factory()->create())
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => AddonVersion::class])
            ->assertSee('Failed')
            ->assertSee('Download returned HTTP 404')
            ->assertSuccessful();
    });
});

describe('live progress', function (): void {
    it('shows the in-progress panel with a queue position for a pending run', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create();
        VerificationResult::factory()->create();
        VerificationResult::factory()->forModVersion($version)->create();

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Pending')
            ->assertSee('Position in queue')
            ->assertSee('This panel updates automatically.')
            ->assertSeeHtml('data-test="verification-progress"')
            ->assertSeeHtml('wire:poll.visible.10s')
            ->assertDontSeeHtml('data-test="verification-submit-button"')
            ->assertSuccessful();
    });

    it('shows the in-progress panel without a queue position for a running run', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create();
        VerificationResult::factory()->forModVersion($version)->running()->create();

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Running')
            ->assertSee('Started')
            ->assertDontSee('Position in queue')
            ->assertSeeHtml('wire:poll.visible.10s')
            ->assertSuccessful();
    });

    it('shows the error result to the mod owner', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create();
        VerificationResult::factory()->forModVersion($version)->errored()->create();

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Error')
            ->assertDontSeeHtml('wire:poll.visible.10s')
            ->assertSuccessful();
    });

    it('shows active runs and polling to guests', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Pending')
            ->assertSee('This panel updates automatically.')
            ->assertSeeHtml('wire:poll.visible.10s')
            ->assertSuccessful();
    });
});

describe('manual submission from the flyout', function (): void {
    it('shows a resubmit button to the mod owner when a result exists', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '>=4.0.0']);
        VerificationResult::factory()->forModVersion($version)->passed()->create();

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSeeHtml('data-test="verification-submit-button"')
            ->assertSee('Resubmit Verification')
            ->assertSuccessful();
    });

    it('shows a submit button to the mod owner when no result exists', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '>=4.0.0']);

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSeeHtml('data-test="verification-submit-button"')
            ->assertSee('Submit for Verification')
            ->assertSuccessful();
    });

    it('hides the submit button from guests and regular users', function (): void {
        $version = ModVersion::factory()->create(['spt_version_constraint' => '>=4.0.0']);
        VerificationResult::factory()->forModVersion($version)->passed()->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertDontSeeHtml('data-test="verification-submit-button"');

        Livewire::actingAs(User::factory()->create())
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertDontSeeHtml('data-test="verification-submit-button"');
    });

    it('hides the submit button for mod versions below the minimum SPT version', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '~3.9.0']);

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertDontSeeHtml('data-test="verification-submit-button"');
    });

    it('queues a manual verification run when the owner submits', function (): void {
        Queue::fake();
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $version = ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '>=4.0.0']);

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->call('submit')
            ->assertDispatched('verification-submitted.mod-version-'.$version->id)
            ->assertSee('Pending')
            ->assertSee('This panel updates automatically.');

        Queue::assertPushed(RunVerificationJob::class);

        expect(VerificationResult::query()
            ->where('verifiable_type', ModVersion::class)
            ->where('verifiable_id', $version->id)
            ->where('trigger', VerificationTrigger::Manual)
            ->exists())->toBeTrue();
    });

    it('queues a manual verification for an unpublished version of an unpublished mod', function (): void {
        Queue::fake();
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id, 'published_at' => null]);
        $version = ModVersion::factory()->recycle($mod)->create([
            'published_at' => null,
            'spt_version_constraint' => '>=4.0.0',
        ]);

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->call('submit');

        expect(VerificationResult::query()->count())->toBe(1);
    });

    it('queues a manual verification for an addon version', function (): void {
        Queue::fake();
        $owner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $owner->id]);
        $version = AddonVersion::factory()->recycle($addon)->create();

        Livewire::actingAs($owner)
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => AddonVersion::class])
            ->call('submit')
            ->assertDispatched('verification-submitted.addon-version-'.$version->id);

        expect(VerificationResult::query()
            ->where('verifiable_type', AddonVersion::class)
            ->where('verifiable_id', $version->id)
            ->exists())->toBeTrue();
    });

    it('forbids submission from users who are not authors', function (): void {
        Queue::fake();
        $version = ModVersion::factory()->create(['spt_version_constraint' => '>=4.0.0']);

        Livewire::actingAs(User::factory()->create())
            ->withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->call('submit')
            ->assertForbidden();

        expect(VerificationResult::query()->count())->toBe(0);
    });
});
