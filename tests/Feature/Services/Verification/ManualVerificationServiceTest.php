<?php

declare(strict_types=1);

use App\Enums\VerificationSubmissionOutcome;
use App\Enums\VerificationTrigger;
use App\Jobs\RunVerificationJob;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;
use App\Services\Verification\ManualVerificationService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    Queue::fake();

    $this->service = resolve(ManualVerificationService::class);
    $this->user = User::factory()->create();
});

describe('manual verification submission', function (): void {
    it('queues a verification run and records a rate limiter attempt', function (): void {
        $version = ModVersion::factory()->create(['spt_version_constraint' => '>=4.0.0']);

        $submission = $this->service->submit($version, $this->user);

        expect($submission->outcome)->toBe(VerificationSubmissionOutcome::Queued)
            ->and($submission->result)->toBeInstanceOf(VerificationResult::class)
            ->and($submission->result->trigger)->toBe(VerificationTrigger::Manual)
            ->and(RateLimiter::attempts('verification-submit:'.$this->user->id))->toBe(1);

        Queue::assertPushed(RunVerificationJob::class);
    });

    it('returns a missing link outcome when the version has no download link', function (): void {
        $version = ModVersion::factory()->create(['link' => '', 'spt_version_constraint' => '>=4.0.0']);

        $submission = $this->service->submit($version, $this->user);

        expect($submission->outcome)->toBe(VerificationSubmissionOutcome::MissingLink)
            ->and(VerificationResult::query()->count())->toBe(0);
    });

    it('returns an ineligible outcome for mod versions below the minimum SPT version', function (): void {
        $version = ModVersion::factory()->create(['spt_version_constraint' => '~3.9.0']);

        $submission = $this->service->submit($version, $this->user);

        expect($submission->outcome)->toBe(VerificationSubmissionOutcome::Ineligible)
            ->and(VerificationResult::query()->count())->toBe(0);
    });

    it('returns an already queued outcome when an active run exists without consuming quota', function (): void {
        $version = ModVersion::factory()->create(['spt_version_constraint' => '>=4.0.0']);
        VerificationResult::factory()->forModVersion($version)->create();

        $submission = $this->service->submit($version, $this->user);

        expect($submission->outcome)->toBe(VerificationSubmissionOutcome::AlreadyQueued)
            ->and(RateLimiter::attempts('verification-submit:'.$this->user->id))->toBe(0)
            ->and(VerificationResult::query()->count())->toBe(1);
    });

    it('rate limits after the configured number of submissions', function (): void {
        config()->set('verification.manual.max_attempts', 2);
        $version = ModVersion::factory()->create(['spt_version_constraint' => '>=4.0.0']);

        foreach (range(1, 2) as $i) {
            RateLimiter::hit('verification-submit:'.$this->user->id, 3600);
        }

        $submission = $this->service->submit($version, $this->user);

        expect($submission->outcome)->toBe(VerificationSubmissionOutcome::RateLimited)
            ->and($submission->retryAfterSeconds)->toBeGreaterThan(0)
            ->and(VerificationResult::query()->count())->toBe(0);
    });

    it('exempts staff from the rate limit', function (): void {
        config()->set('verification.manual.max_attempts', 1);
        $moderator = User::factory()->moderator()->create();
        $version = ModVersion::factory()->create(['spt_version_constraint' => '>=4.0.0']);

        RateLimiter::hit('verification-submit:'.$moderator->id, 3600);

        $submission = $this->service->submit($version, $moderator);

        expect($submission->outcome)->toBe(VerificationSubmissionOutcome::Queued)
            ->and(RateLimiter::attempts('verification-submit:'.$moderator->id))->toBe(1);
    });

    it('queues addon versions without any SPT eligibility gate', function (): void {
        $version = AddonVersion::factory()->create();

        $submission = $this->service->submit($version, $this->user);

        expect($submission->outcome)->toBe(VerificationSubmissionOutcome::Queued)
            ->and($submission->result->verifiable_type)->toBe(AddonVersion::class);
    });

    it('queues unpublished versions of unpublished mods', function (): void {
        $mod = Mod::factory()->create(['published_at' => null]);
        $version = ModVersion::factory()->recycle($mod)->create([
            'published_at' => null,
            'spt_version_constraint' => '>=4.0.0',
        ]);

        $submission = $this->service->submit($version, $this->user);

        expect($submission->outcome)->toBe(VerificationSubmissionOutcome::Queued);
    });

    it('queues disabled versions', function (): void {
        $version = ModVersion::factory()->disabled()->create(['spt_version_constraint' => '>=4.0.0']);

        $submission = $this->service->submit($version, $this->user);

        expect($submission->outcome)->toBe(VerificationSubmissionOutcome::Queued);
    });
});
