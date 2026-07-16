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
use Livewire\Livewire;

it('blocks access for non-admin users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.file-verification'))
        ->assertForbidden();
});

it('allows access for admin users', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.file-verification'))
        ->assertOk();
});

it('displays verification results', function (): void {
    $admin = User::factory()->admin()->create();

    VerificationResult::factory()->passed()->create();
    VerificationResult::factory()->failed()->create();

    $this->actingAs($admin)
        ->get(route('admin.file-verification'))
        ->assertOk()
        ->assertSee('Passed')
        ->assertSee('Failed');
});

it('orders verification results newest first', function (): void {
    $admin = User::factory()->admin()->create();

    $oldest = VerificationResult::factory()->passed()->create(['created_at' => now()->subDays(2)]);
    $newest = VerificationResult::factory()->passed()->create(['created_at' => now()]);

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->assertOk()
        ->assertSeeInOrder([
            $newest->created_at->format('M j, Y H:i'),
            $oldest->created_at->format('M j, Y H:i'),
        ]);
});

it('renders the listing without selecting the large detail columns', function (): void {
    $admin = User::factory()->admin()->create();

    VerificationResult::factory()->failed('A reason that must not leak into the listing')->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->assertOk()
        ->assertDontSee('A reason that must not leak into the listing');
});

it('shows full detail columns in the result modal', function (): void {
    $admin = User::factory()->admin()->create();

    $result = VerificationResult::factory()->passed()->create([
        'download_url' => 'https://example.com/unique-download-url-12345.zip',
        'downloaded_sha256' => 'abcde12345abcde12345abcde12345abcde12345abcde12345abcde12345abcde1',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('showDetails', $result->id)
        ->assertOk()
        ->assertSee('https://example.com/unique-download-url-12345.zip')
        ->assertSee('abcde12345abcde12345abcde12345abcde12345abcde12345abcde12345abcde1');
});

it('shows the per-check results in the modal', function (): void {
    $admin = User::factory()->admin()->create();

    $result = VerificationResult::factory()->passed()->withChecks([
        ['name' => 'archive_extraction', 'status' => 'passed', 'report_only' => false, 'message' => null, 'data' => []],
        ['name' => 'manifest_present', 'status' => 'failed', 'report_only' => true, 'message' => 'No manifest found', 'data' => []],
    ], '4')->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('showDetails', $result->id)
        ->assertOk()
        ->assertSee('Archive Extraction')
        ->assertSee('archive_extraction')
        ->assertSee('Confirms the uploaded archive can be opened')
        ->assertSee('Manifest Present')
        ->assertSee('manifest_present')
        ->assertSee('Report only')
        ->assertSee('No manifest found');
});

it('shows the file download check first among the stored checks in the modal', function (): void {
    $admin = User::factory()->admin()->create();

    $result = VerificationResult::factory()->passed()->withChecks()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('showDetails', $result->id)
        ->assertOk()
        ->assertSeeInOrder(['file_download', 'archive_extraction']);
});

it('shows a failed file download check in the modal when the download failed', function (): void {
    $admin = User::factory()->admin()->create();

    $result = VerificationResult::factory()->failed('Download returned HTTP 404')->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('showDetails', $result->id)
        ->assertOk()
        ->assertSee('File Download')
        ->assertSee('file_download')
        ->assertSee('Confirms the download URL serves the mod archive file directly.')
        ->assertSee('Download returned HTTP 404');
});

it('deletes a verification result and closes the detail modal', function (): void {
    $admin = User::factory()->admin()->create();

    $result = VerificationResult::factory()->passed()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('showDetails', $result->id)
        ->call('deleteResult', $result->id)
        ->assertOk()
        ->assertSet('showDetailModal', false)
        ->assertSet('selectedResultId', null);

    expect(VerificationResult::query()->find($result->id))->toBeNull();
});

it('falls back to the previous completed result when the latest one is deleted', function (): void {
    $admin = User::factory()->admin()->create();

    $version = ModVersion::factory()->create();
    VerificationResult::factory()->forModVersion($version)->passed()->create();
    $failed = VerificationResult::factory()->forModVersion($version)->failed()->create();
    $version->updateQuietly(['verification_status' => VerificationStatus::Failed, 'last_verified_at' => now()]);

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('deleteResult', $failed->id)
        ->assertOk();

    expect(VerificationResult::query()->find($failed->id))->toBeNull()
        ->and($version->refresh())
        ->verification_status->toBe(VerificationStatus::Passed)
        ->last_verified_at->not->toBeNull();
});

it('clears the version verification status when its only result is deleted', function (): void {
    $admin = User::factory()->admin()->create();

    $version = ModVersion::factory()->create();
    $result = VerificationResult::factory()->forModVersion($version)->passed()->create();
    $version->updateQuietly(['verification_status' => VerificationStatus::Passed, 'last_verified_at' => now()]);

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('deleteResult', $result->id)
        ->assertOk();

    expect($version->refresh())
        ->verification_status->toBeNull()
        ->last_verified_at->toBeNull();
});

it('renders the archive file tree as nested nodes in the modal', function (): void {
    $admin = User::factory()->admin()->create();

    $result = VerificationResult::factory()->passed()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('showDetails', $result->id)
        ->assertOk()
        ->assertSee('File Tree')
        ->assertSee('package.json')
        ->assertSee('src')
        ->assertSee('mod.ts')
        ->assertSee('README.md');
});

it('caps the rendered file tree and reports the hidden file count', function (): void {
    $admin = User::factory()->admin()->create();

    $paths = array_map(fn (int $index): string => sprintf('src/file-%04d.ts', $index), range(1, 1005));
    $result = VerificationResult::factory()->passed()->create(['file_tree' => $paths]);

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('showDetails', $result->id)
        ->assertOk()
        ->assertSee('file-1000.ts')
        ->assertDontSee('file-1001.ts')
        ->assertSee('5 more files not shown');
});

it('queues a manual verification for a selected mod version', function (): void {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $mod = Mod::factory()->for(User::factory(), 'owner')->create(['name' => 'Epic Weapons Pack']);
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'spt_version_constraint' => '>=4.0.0',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('openQueueModal')
        ->set('queueSearch', 'Epic')
        ->assertSee('Epic Weapons Pack')
        ->call('selectQueueMod', $mod->id)
        ->assertSee('v'.$modVersion->version)
        ->set('queueModVersionId', $modVersion->id)
        ->call('queueSelectedVersion')
        ->assertOk()
        ->assertSet('showQueueModal', false)
        ->assertSet('queueSearch', '')
        ->assertSet('queueModId', null)
        ->assertSet('queueModVersionId', null);

    Queue::assertPushed(RunVerificationJob::class);

    expect(VerificationResult::query()
        ->where('verifiable_type', ModVersion::class)
        ->where('verifiable_id', $modVersion->id)
        ->where('trigger', VerificationTrigger::Manual)
        ->where('status', VerificationStatus::Pending)
        ->exists())->toBeTrue();
});

it('does not queue a duplicate verification when one is already pending', function (): void {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $mod = Mod::factory()->for(User::factory(), 'owner')->create(['name' => 'Epic Weapons Pack']);
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'spt_version_constraint' => '>=4.0.0',
    ]);

    VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('selectQueueMod', $mod->id)
        ->set('queueModVersionId', $modVersion->id)
        ->call('queueSelectedVersion')
        ->assertOk()
        ->assertSet('showQueueModal', false)
        ->assertSet('queueSearch', '')
        ->assertSet('queueModId', null)
        ->assertSet('queueModVersionId', null);

    Queue::assertNotPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->where('verifiable_id', $modVersion->id)->count())->toBe(1);
});

it('does not queue a verification for a version only compatible with SPT versions below the minimum', function (): void {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $mod = Mod::factory()->for(User::factory(), 'owner')->create(['name' => 'Epic Weapons Pack']);
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'spt_version_constraint' => '~3.9.0',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('selectQueueMod', $mod->id)
        ->set('queueModVersionId', $modVersion->id)
        ->call('queueSelectedVersion')
        ->assertOk()
        ->assertSet('showQueueModal', false)
        ->assertSet('queueSearch', '')
        ->assertSet('queueModId', null)
        ->assertSet('queueModVersionId', null);

    Queue::assertNotPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->where('verifiable_id', $modVersion->id)->count())->toBe(0);
});

it('excludes mods without downloadable versions from the queue search', function (): void {
    $admin = User::factory()->admin()->create();
    $mod = Mod::factory()->for(User::factory(), 'owner')->create(['name' => 'Linkless Mod']);
    ModVersion::factory()->for($mod)->create(['link' => '']);

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('openQueueModal')
        ->set('queueSearch', 'Linkless')
        ->assertSee('No mods with downloadable versions match the search')
        ->assertDontSee('Linkless Mod');
});

it('excludes versions without a download link from the version list', function (): void {
    $admin = User::factory()->admin()->create();
    $mod = Mod::factory()->for(User::factory(), 'owner')->create(['name' => 'Epic Weapons Pack']);
    ModVersion::factory()->for($mod)->create(['version' => '1.0.0', 'link' => 'https://example.com/mod.zip']);
    ModVersion::factory()->for($mod)->create(['version' => '2.0.0', 'link' => '']);

    Livewire::actingAs($admin)
        ->test('pages::admin.file-verification')
        ->call('openQueueModal')
        ->call('selectQueueMod', $mod->id)
        ->assertSee('v1.0.0')
        ->assertDontSee('v2.0.0');
});
