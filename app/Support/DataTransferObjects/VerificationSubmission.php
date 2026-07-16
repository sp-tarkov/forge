<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Enums\VerificationSubmissionOutcome;
use App\Models\VerificationResult;

/**
 * Value object representing the result of a manual verification submission.
 */
final readonly class VerificationSubmission
{
    public function __construct(
        public VerificationSubmissionOutcome $outcome,
        public ?int $retryAfterSeconds = null,
        public ?VerificationResult $result = null,
    ) {}
}
