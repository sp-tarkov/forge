<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the spam detection status of a comment.
 */
enum SpamStatus: string
{
    /**
     * Comment has not been checked for spam yet.
     */
    case PENDING = 'pending';

    /**
     * Comment has been verified as clean (not spam).
     */
    case CLEAN = 'clean';

    /**
     * Comment has been identified as spam.
     */
    case SPAM = 'spam';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CLEAN => 'Clean',
            self::SPAM => 'Spam',
        };
    }

    /**
     * Get the color associated with this status (for UI).
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::CLEAN => 'green',
            self::SPAM => 'red',
        };
    }

    /**
     * Check if this status represents spam.
     */
    public function isSpam(): bool
    {
        return $this === self::SPAM;
    }

    /**
     * Check if this status represents clean content.
     */
    public function isClean(): bool
    {
        return $this === self::CLEAN;
    }

    /**
     * Check if this status is pending verification.
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }
}
