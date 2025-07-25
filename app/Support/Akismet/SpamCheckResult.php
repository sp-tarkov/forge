<?php

declare(strict_types=1);

namespace App\Support\Akismet;

use App\Enums\SpamStatus;

/**
 * Represents the result of a spam check operation.
 */
readonly class SpamCheckResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $isSpam,
        public array $metadata,
        public bool $discard = false,
        public ?string $proTip = null,
        public ?string $recheckAfter = null
    ) {}

    /**
     * Determine if this comment should be auto-deleted (based on the discard flag).
     */
    public function shouldAutoDelete(): bool
    {
        return $this->isSpam && $this->discard;
    }

    /**
     * Get the recommended action for this spam check result.
     */
    public function getRecommendedAction(): string
    {
        if ($this->shouldAutoDelete()) {
            return 'delete';
        }

        if ($this->isSpam) {
            return 'review';
        }

        return 'approve';
    }

    /**
     * Get the spam status enum value for the database.
     */
    public function getSpamStatus(): SpamStatus
    {
        if ($this->discard || $this->shouldAutoDelete()) {
            return SpamStatus::SPAM;
        }

        return $this->isSpam ? SpamStatus::SPAM : SpamStatus::CLEAN;
    }
}
