<?php

declare(strict_types=1);

use App\Console\Commands\VerifyVersionCommand;
use App\Enums\VerificationStatus;
use App\Jobs\RunVerificationJob;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;
use Illuminate\Support\Facades\Queue;

it('dispatches verification job for a valid mod version', function (): void {
    Queue::fake([RunVerificationJob::class]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'spt_version_constraint' => '>=4.0.0',
    ]);

    $this->artisan(VerifyVersionCommand::class, ['type' => 'mod_version', 'id' => $modVersion->id])
        ->expectsOutput(sprintf('Verification job dispatched for mod_version #%d.', $modVersion->id))
        ->assertSuccessful();

    Queue::assertPushedOn('verification', RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(1);
    expect(VerificationResult::query()->first())
        ->status->toBe(VerificationStatus::Pending);
});

it('fails for non-existent version', function (): void {
    $this->artisan(VerifyVersionCommand::class, ['type' => 'mod_version', 'id' => 99999])
        ->assertFailed();
});

it('fails for invalid type', function (): void {
    $this->artisan(VerifyVersionCommand::class, ['type' => 'invalid_type', 'id' => 1])
        ->assertFailed();
});

it('fails for version with no download link', function (): void {
    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => '',
    ]);

    $this->artisan(VerifyVersionCommand::class, ['type' => 'mod_version', 'id' => $modVersion->id])
        ->assertFailed();
});

it('fails for a mod version only compatible with SPT versions below the minimum', function (): void {
    Queue::fake([RunVerificationJob::class]);

    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => 'https://example.com/mod.zip',
        'spt_version_constraint' => '~3.9.0',
    ]);

    $this->artisan(VerifyVersionCommand::class, ['type' => 'mod_version', 'id' => $modVersion->id])
        ->expectsOutput('This version is not eligible for verification. Only versions compatible with SPT 4.0.0 or newer are verified.')
        ->assertFailed();

    Queue::assertNotPushed(RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(0);
});
