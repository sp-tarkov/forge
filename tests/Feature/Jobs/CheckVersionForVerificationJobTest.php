<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Enums\VerificationTrigger;
use App\Jobs\CheckVersionForVerificationJob;
use App\Jobs\RunVerificationJob;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;
use App\Services\Verification\ChangeDetectionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('dispatches verification job when download link has changed', function (): void {
    Queue::fake([RunVerificationJob::class]);

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '99999',
            'ETag' => '"new-etag"',
        ]),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
        'etag' => null,
        'last_verified_at' => null,
        'spt_version_constraint' => '>=4.0.0',
    ]);

    new CheckVersionForVerificationJob(ModVersion::class, $modVersion->id)->handle(resolve(ChangeDetectionService::class));

    Queue::assertPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(1);
    expect(VerificationResult::query()->first())
        ->status->toBe(VerificationStatus::Pending);
});

it('does not dispatch when no change detected', function (): void {
    Queue::fake([RunVerificationJob::class]);

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '12345',
            'ETag' => '"same-etag"',
        ]),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
        'content_length' => 12345,
        'etag' => '"same-etag"',
        'last_verified_at' => now(),
        'spt_version_constraint' => '>=4.0.0',
    ]);

    new CheckVersionForVerificationJob(ModVersion::class, $modVersion->id)->handle(resolve(ChangeDetectionService::class));

    Queue::assertNotPushed(RunVerificationJob::class);
});

it('skips versions that already have a pending verification', function (): void {
    Queue::fake([RunVerificationJob::class]);

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '99999',
            'ETag' => '"new-etag"',
        ]),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
        'etag' => null,
        'last_verified_at' => null,
        'spt_version_constraint' => '>=4.0.0',
    ]);

    VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new CheckVersionForVerificationJob(ModVersion::class, $modVersion->id)->handle(resolve(ChangeDetectionService::class));

    Queue::assertNotPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(1);
});

it('updates fingerprint columns when values change', function (): void {
    Queue::fake([RunVerificationJob::class]);

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '54321',
            'ETag' => '"fresh-etag"',
            'Last-Modified' => 'Thu, 10 Apr 2025 12:00:00 GMT',
        ]),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
        'etag' => null,
        'last_verified_at' => null,
        'spt_version_constraint' => '>=4.0.0',
    ]);

    new CheckVersionForVerificationJob(ModVersion::class, $modVersion->id)->handle(resolve(ChangeDetectionService::class));

    $modVersion->refresh();

    expect($modVersion->content_length)->toBe(54321);
    expect($modVersion->etag)->toBe('"fresh-etag"');
    expect($modVersion->last_modified_header)->toBe('Thu, 10 Apr 2025 12:00:00 GMT');
});

it('skips mod versions only compatible with SPT versions below the minimum without making a request', function (): void {
    Queue::fake([RunVerificationJob::class]);
    Http::fake();

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
        'etag' => null,
        'last_verified_at' => null,
        'spt_version_constraint' => '~3.9.0',
    ]);

    new CheckVersionForVerificationJob(ModVersion::class, $modVersion->id)->handle(resolve(ChangeDetectionService::class));

    Http::assertNothingSent();
    Queue::assertNotPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(0);
});

it('silently returns when version does not exist', function (): void {
    Queue::fake([RunVerificationJob::class]);

    new CheckVersionForVerificationJob(ModVersion::class, 99999)->handle(resolve(ChangeDetectionService::class));

    Queue::assertNotPushed(RunVerificationJob::class);
});

it('dispatches verification with the checks updated trigger when the latest completed result is on an older checks version', function (): void {
    Queue::fake([RunVerificationJob::class]);

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '12345',
            'ETag' => '"same-etag"',
        ]),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
        'content_length' => 12345,
        'etag' => '"same-etag"',
        'last_verified_at' => now(),
        'spt_version_constraint' => '>=4.0.0',
    ]);

    VerificationResult::factory()->forModVersion($modVersion)->passed()->withChecks(version: '3')->create();

    new CheckVersionForVerificationJob(ModVersion::class, $modVersion->id)->handle(resolve(ChangeDetectionService::class));

    Queue::assertPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(2);
    expect(VerificationResult::query()->latest('id')->first())
        ->status->toBe(VerificationStatus::Pending)
        ->trigger->toBe(VerificationTrigger::ChecksUpdated);
});

it('does not dispatch when the latest completed result matches the latest checks version', function (): void {
    Queue::fake([RunVerificationJob::class]);

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '12345',
            'ETag' => '"same-etag"',
        ]),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
        'content_length' => 12345,
        'etag' => '"same-etag"',
        'last_verified_at' => now(),
        'spt_version_constraint' => '>=4.0.0',
    ]);

    VerificationResult::factory()
        ->forModVersion($modVersion)
        ->passed()
        ->withChecks(version: RunVerificationJob::LATEST_CHECKS_VERSION)
        ->create();

    new CheckVersionForVerificationJob(ModVersion::class, $modVersion->id)->handle(resolve(ChangeDetectionService::class));

    Queue::assertNotPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(1);
});

it('does not dispatch when the latest completed result has no checks version', function (): void {
    Queue::fake([RunVerificationJob::class]);

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '12345',
            'ETag' => '"same-etag"',
        ]),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
        'content_length' => 12345,
        'etag' => '"same-etag"',
        'last_verified_at' => now(),
        'spt_version_constraint' => '>=4.0.0',
    ]);

    VerificationResult::factory()->forModVersion($modVersion)->failed()->create();

    new CheckVersionForVerificationJob(ModVersion::class, $modVersion->id)->handle(resolve(ChangeDetectionService::class));

    Queue::assertNotPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(1);
});

it('does not dispatch for an older checks version when the link is unreachable', function (): void {
    Queue::fake([RunVerificationJob::class]);

    Http::fake([
        '*' => Http::response('', 500),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
        'content_length' => 12345,
        'etag' => '"same-etag"',
        'last_verified_at' => now(),
        'spt_version_constraint' => '>=4.0.0',
    ]);

    VerificationResult::factory()->forModVersion($modVersion)->passed()->withChecks(version: '3')->create();

    new CheckVersionForVerificationJob(ModVersion::class, $modVersion->id)->handle(resolve(ChangeDetectionService::class));

    Queue::assertNotPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(1);
});

it('dispatches with the change detected trigger when the file changed and the checks version is also outdated', function (): void {
    Queue::fake([RunVerificationJob::class]);

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '99999',
            'ETag' => '"new-etag"',
        ]),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
        'content_length' => 12345,
        'etag' => '"old-etag"',
        'last_verified_at' => now(),
        'spt_version_constraint' => '>=4.0.0',
    ]);

    VerificationResult::factory()->forModVersion($modVersion)->passed()->withChecks(version: '3')->create();

    new CheckVersionForVerificationJob(ModVersion::class, $modVersion->id)->handle(resolve(ChangeDetectionService::class));

    Queue::assertPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(2);
    expect(VerificationResult::query()->latest('id')->first())
        ->trigger->toBe(VerificationTrigger::ChangeDetected);
});

it('does not dispatch for an older checks version when a fresh pending verification exists', function (): void {
    Queue::fake([RunVerificationJob::class]);

    Http::fake([
        '*' => Http::response('', 200, [
            'Content-Length' => '12345',
            'ETag' => '"same-etag"',
        ]),
    ]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'published_at' => now(),
        'disabled' => false,
        'content_length' => 12345,
        'etag' => '"same-etag"',
        'last_verified_at' => now(),
        'spt_version_constraint' => '>=4.0.0',
    ]);

    VerificationResult::factory()->forModVersion($modVersion)->passed()->withChecks(version: '3')->create();
    VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
    ]);

    new CheckVersionForVerificationJob(ModVersion::class, $modVersion->id)->handle(resolve(ChangeDetectionService::class));

    Queue::assertNotPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(2);
});
