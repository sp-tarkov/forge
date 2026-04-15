<?php

declare(strict_types=1);

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
    ]);

    $this->artisan('app:verify-version', ['type' => 'mod_version', 'id' => $modVersion->id])
        ->expectsOutput(sprintf('Verification job dispatched for mod_version #%d.', $modVersion->id))
        ->assertSuccessful();

    Queue::assertPushedOn('verification', RunVerificationJob::class);
    expect(VerificationResult::query()->count())->toBe(1);
    expect(VerificationResult::query()->first())
        ->status->toBe(VerificationStatus::Pending);
});

it('fails for non-existent version', function (): void {
    $this->artisan('app:verify-version', ['type' => 'mod_version', 'id' => 99999])
        ->assertFailed();
});

it('fails for invalid type', function (): void {
    $this->artisan('app:verify-version', ['type' => 'invalid_type', 'id' => 1])
        ->assertFailed();
});

it('fails for version with no download link', function (): void {
    $mod = Mod::factory()->for(User::factory(), 'owner')->create();
    $modVersion = ModVersion::factory()->for($mod)->create([
        'link' => '',
    ]);

    $this->artisan('app:verify-version', ['type' => 'mod_version', 'id' => $modVersion->id])
        ->assertFailed();
});
