<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Enums\VerificationTrigger;
use App\Jobs\RunVerificationJob;
use App\Models\AddonVersion;
use App\Models\VerificationResult;
use Illuminate\Support\Facades\Queue;

describe('upload verification', function (): void {
    it('dispatches an upload verification when an addon version is created', function (): void {
        config()->set('verification.auto_enabled', true);
        Queue::fake();

        $addonVersion = AddonVersion::factory()->create([
            'link' => 'https://example.com/addon.zip',
            'disabled' => false,
        ]);

        Queue::assertPushed(RunVerificationJob::class);

        expect(VerificationResult::query()
            ->where('verifiable_type', AddonVersion::class)
            ->where('verifiable_id', $addonVersion->id)
            ->where('trigger', VerificationTrigger::Upload)
            ->where('status', VerificationStatus::Pending)
            ->exists())->toBeTrue();
    });

    it('does not dispatch when automatic verification is disabled', function (): void {
        config()->set('verification.auto_enabled', false);
        Queue::fake();

        AddonVersion::factory()->create(['link' => 'https://example.com/addon.zip']);

        Queue::assertNotPushed(RunVerificationJob::class);
        expect(VerificationResult::query()->count())->toBe(0);
    });

    it('does not dispatch for a version without a download link', function (): void {
        config()->set('verification.auto_enabled', true);
        Queue::fake();

        AddonVersion::factory()->create(['link' => '']);

        Queue::assertNotPushed(RunVerificationJob::class);
    });

    it('does not dispatch for a disabled version', function (): void {
        config()->set('verification.auto_enabled', true);
        Queue::fake();

        AddonVersion::factory()->disabled()->create(['link' => 'https://example.com/addon.zip']);

        Queue::assertNotPushed(RunVerificationJob::class);
    });
});

describe('link change verification', function (): void {
    beforeEach(function (): void {
        config()->set('verification.auto_enabled', false);

        $this->version = AddonVersion::factory()->create([
            'link' => 'https://example.com/addon.zip',
            'disabled' => false,
        ]);
        $this->version->updateQuietly([
            'verification_status' => VerificationStatus::Passed,
            'last_verified_at' => now(),
        ]);
    });

    it('clears the denormalized status and queues a run when the link changes', function (): void {
        config()->set('verification.auto_enabled', true);
        Queue::fake();

        $this->version->update(['link' => 'https://example.com/addon-v2.zip']);

        expect($this->version->refresh())
            ->verification_status->toBeNull()
            ->last_verified_at->toBeNull();

        Queue::assertPushed(RunVerificationJob::class);

        expect(VerificationResult::query()
            ->where('verifiable_type', AddonVersion::class)
            ->where('verifiable_id', $this->version->id)
            ->where('trigger', VerificationTrigger::LinkUpdated)
            ->exists())->toBeTrue();
    });

    it('clears the denormalized status without queueing when automatic verification is disabled', function (): void {
        Queue::fake();

        $this->version->update(['link' => 'https://example.com/addon-v2.zip']);

        expect($this->version->refresh())
            ->verification_status->toBeNull()
            ->last_verified_at->toBeNull();

        Queue::assertNotPushed(RunVerificationJob::class);
        expect(VerificationResult::query()->count())->toBe(0);
    });

    it('does not touch the verification status when other fields change', function (): void {
        config()->set('verification.auto_enabled', true);
        Queue::fake();

        $this->version->update(['description' => 'Updated description']);

        expect($this->version->refresh())
            ->verification_status->toBe(VerificationStatus::Passed)
            ->last_verified_at->not->toBeNull();

        Queue::assertNotPushed(RunVerificationJob::class);
    });
});
