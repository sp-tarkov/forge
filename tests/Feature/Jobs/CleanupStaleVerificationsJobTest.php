<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Jobs\CleanupStaleVerificationsJob;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;

it('marks stale pending results as errored', function (): void {
    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create();

    $stale = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
        'created_at' => now()->subHours(25),
        'updated_at' => now()->subHours(25),
    ]);

    new CleanupStaleVerificationsJob()->handle();

    $stale->refresh();

    expect($stale->status)->toBe(VerificationStatus::Error);
    expect($stale->failure_reason)->toContain('stale');
    expect($stale->completed_at)->not->toBeNull();
});

it('marks stale running results as errored', function (): void {
    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create();

    $stale = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Running,
        'created_at' => now()->subMinutes(120),
        'updated_at' => now()->subMinutes(120),
    ]);

    new CleanupStaleVerificationsJob()->handle();

    $stale->refresh();

    expect($stale->status)->toBe(VerificationStatus::Error);
    expect($stale->failure_reason)->toContain('stale');
});

it('leaves fresh pending and running results untouched', function (): void {
    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create();

    $pending = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Pending,
        'created_at' => now()->subHours(12),
        'updated_at' => now()->subHours(12),
    ]);

    $running = VerificationResult::factory()->forModVersion($modVersion)->create([
        'status' => VerificationStatus::Running,
        'created_at' => now()->subMinutes(30),
        'updated_at' => now()->subMinutes(30),
    ]);

    new CleanupStaleVerificationsJob()->handle();

    expect($pending->refresh()->status)->toBe(VerificationStatus::Pending);
    expect($running->refresh()->status)->toBe(VerificationStatus::Running);
});

it('leaves completed results untouched regardless of age', function (): void {
    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create();

    $passed = VerificationResult::factory()->forModVersion($modVersion)->passed()->create([
        'created_at' => now()->subDays(30),
        'updated_at' => now()->subDays(30),
    ]);

    $failed = VerificationResult::factory()->forModVersion($modVersion)->failed()->create([
        'created_at' => now()->subDays(30),
        'updated_at' => now()->subDays(30),
    ]);

    new CleanupStaleVerificationsJob()->handle();

    expect($passed->refresh()->status)->toBe(VerificationStatus::Passed);
    expect($failed->refresh()->status)->toBe(VerificationStatus::Failed);
});
