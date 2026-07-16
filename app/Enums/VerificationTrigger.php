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
     * Triggered when a new mod or addon version is created.
     */
    case Upload = 'upload';

    /**
     * Triggered when a version's download link is changed.
     */
    case LinkUpdated = 'link_updated';

    /**
     * Get a human-readable label for the trigger.
     */
    public function label(): string
    {
        return match ($this) {
            self::ChangeDetected => 'Change Detected',
            self::Manual => 'Manual',
            self::Upload => 'Upload',
            self::LinkUpdated => 'Link Updated',
        };
    }
}
