<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;
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
            ->assertSee('Confirms your uploaded archive can be opened')
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

    it('does not expose failure information from other runs', function (): void {
        $version = ModVersion::factory()->create();
        $passed = VerificationResult::factory()->forModVersion($version)->passed()->create();
        VerificationResult::factory()->forModVersion($version)->failed('Internal failure diagnostics')->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee($passed->downloaded_sha256)
            ->assertDontSee('Internal failure diagnostics')
            ->assertSuccessful();
    });

    it('shows the latest passed result when a newer pending run exists', function (): void {
        $version = ModVersion::factory()->create();
        $passed = VerificationResult::factory()->forModVersion($version)->passed()->create();
        VerificationResult::factory()->forModVersion($version)->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Passed')
            ->assertSee($passed->downloaded_sha256)
            ->assertSuccessful();
    });

    it('shows a fallback message when no passed result exists', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->failed()->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Verification details are currently unavailable.')
            ->assertSuccessful();
    });

    it('shows a fallback message for regular users when only a failed result exists', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->failed('Download returned HTTP 404')->create();

        $this->actingAs(User::factory()->create());

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertSee('Verification details are currently unavailable.')
            ->assertDontSee('Download returned HTTP 404')
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
            ->assertSee('Confirms your download URL serves the mod archive file directly.')
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
