<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Enums\VerificationTrigger;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;

it('creates a verification result with factory defaults', function (): void {
    $result = VerificationResult::factory()->create();

    expect($result)
        ->status->toBe(VerificationStatus::Pending)
        ->trigger->toBe(VerificationTrigger::Manual)
        ->download_url->not->toBeEmpty();
});

it('creates a passed verification result', function (): void {
    $result = VerificationResult::factory()->passed()->create();

    expect($result)
        ->status->toBe(VerificationStatus::Passed)
        ->download_ok->toBeTrue()
        ->archive_ok->toBeTrue()
        ->file_tree->toBeArray()
        ->started_at->not->toBeNull()
        ->completed_at->not->toBeNull();
});

it('creates a failed verification result', function (): void {
    $result = VerificationResult::factory()->failed('Test failure')->create();

    expect($result)
        ->status->toBe(VerificationStatus::Failed)
        ->download_ok->toBeFalse()
        ->failure_reason->toBe('Test failure');
});

it('belongs to a mod version via polymorphic relationship', function (): void {
    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create();

    $result = VerificationResult::factory()->forModVersion($modVersion)->create();

    expect($result->verifiable)
        ->toBeInstanceOf(ModVersion::class)
        ->id->toBe($modVersion->id);
});

it('can be accessed from mod version via verification results relationship', function (): void {
    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create();

    VerificationResult::factory()->forModVersion($modVersion)->passed()->create();
    VerificationResult::factory()->forModVersion($modVersion)->failed()->create();

    expect($modVersion->verificationResults)->toHaveCount(2);
    expect($modVersion->latestVerificationResult)->not->toBeNull();
});

it('casts status and trigger to enums', function (): void {
    $result = VerificationResult::factory()->create([
        'status' => VerificationStatus::Running,
        'trigger' => VerificationTrigger::ChangeDetected,
    ]);

    $result->refresh();

    expect($result->status)->toBe(VerificationStatus::Running);
    expect($result->trigger)->toBe(VerificationTrigger::ChangeDetected);
});

it('casts file_tree and details to arrays', function (): void {
    $result = VerificationResult::factory()->create([
        'file_tree' => ['package.json', 'src/mod.ts'],
        'details' => ['download' => ['duration_seconds' => 1.5]],
    ]);

    $result->refresh();

    expect($result->file_tree)->toBe(['package.json', 'src/mod.ts']);
    expect($result->details)->toBe(['download' => ['duration_seconds' => 1.5]]);
});
