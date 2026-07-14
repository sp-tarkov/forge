<?php

declare(strict_types=1);

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
            ->assertSee('suite 4')
            ->assertSuccessful();
    });

    it('omits the checks section when the result has no checks', function (): void {
        $version = ModVersion::factory()->create();
        VerificationResult::factory()->forModVersion($version)->passed()->create();

        Livewire::withoutLazyLoading()
            ->test('verification-details', ['verifiableId' => $version->id, 'verifiableType' => ModVersion::class])
            ->assertDontSee('Checks')
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
