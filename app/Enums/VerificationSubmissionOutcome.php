<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the outcome of a manual verification submission.
 */
enum VerificationSubmissionOutcome: string
{
    /**
     * A verification run was queued for the version.
     */
    case Queued = 'queued';

    /**
     * An active verification run already exists for the version.
     */
    case AlreadyQueued = 'already_queued';

    /**
     * The user has exceeded the manual submission rate limit.
     */
    case RateLimited = 'rate_limited';

    /**
     * The version is not eligible for verification.
     */
    case Ineligible = 'ineligible';

    /**
     * The version has no download link to verify.
     */
    case MissingLink = 'missing_link';

    /**
     * Get the toast heading for the outcome.
     */
    public function toastHeading(): string
    {
        return match ($this) {
            self::Queued => 'Verification Queued',
            self::AlreadyQueued => 'Already Pending',
            self::RateLimited => 'Too Many Submissions',
            self::Ineligible => 'Not Eligible',
            self::MissingLink => 'Error',
        };
    }

    /**
     * Get the toast message for the outcome.
     */
    public function toastText(?int $retryAfterSeconds = null): string
    {
        return match ($this) {
            self::Queued => 'A verification job has been queued for this version.',
            self::AlreadyQueued => 'A verification is already pending for this version.',
            self::RateLimited => self::rateLimitedText($retryAfterSeconds),
            self::Ineligible => sprintf(
                'Verification only runs for versions compatible with SPT %s or newer.',
                config()->string('verification.min_spt_version', '4.0.0'),
            ),
            self::MissingLink => 'This version has no download link to verify.',
        };
    }

    /**
     * Get the Flux toast variant for the outcome.
     */
    public function toastVariant(): string
    {
        return match ($this) {
            self::Queued => 'success',
            self::AlreadyQueued, self::RateLimited, self::Ineligible => 'warning',
            self::MissingLink => 'danger',
        };
    }

    /**
     * Get the rate-limited toast message with the retry delay rounded up to whole minutes.
     */
    private static function rateLimitedText(?int $retryAfterSeconds): string
    {
        $minutes = max(1, (int) ceil(($retryAfterSeconds ?? 0) / 60));

        return sprintf(
            'You have submitted too many verifications. Please try again in %d %s.',
            $minutes,
            $minutes === 1 ? 'minute' : 'minutes',
        );
    }
}
