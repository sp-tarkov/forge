<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the status of a file verification result.
 */
enum VerificationStatus: string
{
    /**
     * Verification job is queued and waiting to be processed.
     */
    case Pending = 'pending';

    /**
     * Verification job is currently being processed.
     */
    case Running = 'running';

    /**
     * All verification checks passed successfully.
     */
    case Passed = 'passed';

    /**
     * One or more verification checks failed.
     */
    case Failed = 'failed';

    /**
     * An unexpected error occurred during verification.
     */
    case Error = 'error';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
            self::Error => 'Error',
        };
    }

    /**
     * Get the icon name for this status (for UI).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Running => 'arrow-path',
            self::Passed => 'check-circle',
            self::Failed => 'x-circle',
            self::Error => 'exclamation-triangle',
        };
    }

    /**
     * Get the Flux badge color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'blue',
            self::Passed => 'green',
            self::Failed => 'red',
            self::Error => 'amber',
        };
    }
}
