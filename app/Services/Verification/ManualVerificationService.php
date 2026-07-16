<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\VerificationSubmissionOutcome;
use App\Enums\VerificationTrigger;
use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\VerificationResult;
use App\Support\DataTransferObjects\VerificationSubmission;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Handles manual verification submissions from authors and staff, applying eligibility checks and a per-user rate
 * limit before dispatching a verification run. Unpublished and disabled versions are intentionally submittable.
 */
final readonly class ManualVerificationService
{
    /**
     * Submit a version for verification and return the outcome.
     */
    public function submit(ModVersion|AddonVersion $version, User $user): VerificationSubmission
    {
        if ($version->link === '') {
            return new VerificationSubmission(VerificationSubmissionOutcome::MissingLink);
        }

        if ($version instanceof ModVersion && ! $version->isEligibleForVerification()) {
            return new VerificationSubmission(VerificationSubmissionOutcome::Ineligible);
        }

        $exemptFromRateLimit = $user->isModOrAdmin();
        $rateLimitKey = 'verification-submit:'.$user->id;

        if (! $exemptFromRateLimit && RateLimiter::tooManyAttempts($rateLimitKey, config()->integer('verification.manual.max_attempts', 5))) {
            return new VerificationSubmission(
                VerificationSubmissionOutcome::RateLimited,
                retryAfterSeconds: RateLimiter::availableIn($rateLimitKey),
            );
        }

        $result = VerificationResult::dispatchFor($version, VerificationTrigger::Manual);

        if (! $result instanceof VerificationResult) {
            return new VerificationSubmission(VerificationSubmissionOutcome::AlreadyQueued);
        }

        if (! $exemptFromRateLimit) {
            RateLimiter::hit($rateLimitKey, config()->integer('verification.manual.decay_seconds', 3600));
        }

        return new VerificationSubmission(VerificationSubmissionOutcome::Queued, result: $result);
    }
}
