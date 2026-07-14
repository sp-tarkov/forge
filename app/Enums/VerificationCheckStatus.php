<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the outcome of an individual check within a verification run.
 */
enum VerificationCheckStatus: string
{
    /**
     * The check ran and the archive satisfied it.
     */
    case Passed = 'passed';

    /**
     * The check ran and the archive violated it.
     */
    case Failed = 'failed';

    /**
     * The check did not run for this archive.
     */
    case Skipped = 'skipped';

    /**
     * Resolve a status reported by the container, falling back to a failure for any unrecognized value.
     */
    public static function fromContainer(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Failed;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Passed',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    /**
     * Get the Flux badge color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Passed => 'green',
            self::Failed => 'red',
            self::Skipped => 'gray',
        };
    }
}
