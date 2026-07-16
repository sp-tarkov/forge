<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\VerificationResultUpdated;
use App\Models\VerificationResult;

final class VerificationResultObserver
{
    /**
     * Handle the VerificationResult "saved" event.
     */
    public function saved(VerificationResult $verificationResult): void
    {
        event(new VerificationResultUpdated(
            $verificationResult->id,
            $verificationResult->verifiable_id,
            $verificationResult->verifiable_type,
            $verificationResult->status->value
        ));
    }

    /**
     * Handle the VerificationResult "deleted" event.
     */
    public function deleted(VerificationResult $verificationResult): void
    {
        event(new VerificationResultUpdated(
            $verificationResult->id,
            $verificationResult->verifiable_id,
            $verificationResult->verifiable_type,
            null
        ));
    }
}
