<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents what triggered a file verification.
 */
enum VerificationTrigger: string
{
    /**
     * Triggered by the scheduled change detection job.
     */
    case ChangeDetected = 'change_detected';

    /**
     * Triggered manually by an administrator.
     */
    case Manual = 'manual';

    /**
     * Get a human-readable label for the trigger.
     */
    public function label(): string
    {
        return match ($this) {
            self::ChangeDetected => 'Change Detected',
            self::Manual => 'Manual',
        };
    }
}
