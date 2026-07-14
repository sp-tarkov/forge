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

beforeEach(function (): void {
    $this->mod = Mod::factory()->for(User::factory(), 'owner')->create();
});

describe('upload verification', function (): void {
    it('dispatches an upload verification when a mod version is created', function (): void {
        config()->set('verification.auto_enabled', true);
        Queue::fake();

        $modVersion = ModVersion::factory()->for($this->mod)->create([
            'link' => 'https://example.com/mod.zip',
            'disabled' => false,
        ]);

        Queue::assertPushed(RunVerificationJob::class);

        expect(VerificationResult::query()
            ->where('verifiable_type', ModVersion::class)
            ->where('verifiable_id', $modVersion->id)
            ->where('trigger', VerificationTrigger::Upload)
            ->where('status', VerificationStatus::Pending)
            ->exists())->toBeTrue();
    });

    it('does not dispatch when automatic verification is disabled', function (): void {
        config()->set('verification.auto_enabled', false);
        Queue::fake();

        ModVersion::factory()->for($this->mod)->create(['link' => 'https://example.com/mod.zip']);

        Queue::assertNotPushed(RunVerificationJob::class);
        expect(VerificationResult::query()->count())->toBe(0);
    });

    it('does not dispatch for a version without a download link', function (): void {
        config()->set('verification.auto_enabled', true);
        Queue::fake();

        ModVersion::factory()->for($this->mod)->create(['link' => '']);

        Queue::assertNotPushed(RunVerificationJob::class);
    });

    it('does not dispatch for a disabled version', function (): void {
        config()->set('verification.auto_enabled', true);
        Queue::fake();

        ModVersion::factory()->for($this->mod)->disabled()->create(['link' => 'https://example.com/mod.zip']);

        Queue::assertNotPushed(RunVerificationJob::class);
    });
});
