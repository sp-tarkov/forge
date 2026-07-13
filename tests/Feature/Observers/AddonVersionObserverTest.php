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
        config()->set('verification.enabled', true);
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

    it('does not dispatch when the verification pipeline is disabled', function (): void {
        config()->set('verification.enabled', false);
        Queue::fake();

        AddonVersion::factory()->create(['link' => 'https://example.com/addon.zip']);

        Queue::assertNotPushed(RunVerificationJob::class);
        expect(VerificationResult::query()->count())->toBe(0);
    });

    it('does not dispatch for a version without a download link', function (): void {
        config()->set('verification.enabled', true);
        Queue::fake();

        AddonVersion::factory()->create(['link' => '']);

        Queue::assertNotPushed(RunVerificationJob::class);
    });

    it('does not dispatch for a disabled version', function (): void {
        config()->set('verification.enabled', true);
        Queue::fake();

        AddonVersion::factory()->disabled()->create(['link' => 'https://example.com/addon.zip']);

        Queue::assertNotPushed(RunVerificationJob::class);
    });
});
