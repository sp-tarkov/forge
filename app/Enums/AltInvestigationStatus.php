<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle status of a queued alt-detection investigation run.
 */
enum AltInvestigationStatus: string
{
    /**
     * The investigation job is queued and waiting to be processed.
     */
    case Pending = 'pending';

    /**
     * The investigation job is currently running.
     */
    case Processing = 'processing';

    /**
     * The investigation finished and its results are stored.
     */
    case Completed = 'completed';

    /**
     * The investigation job failed before producing results.
     */
    case Failed = 'failed';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Processing => 'Analyzing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
