<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the Fika compatibility status of a mod version.
 */
enum FikaCompatibilityStatus: string
{
    /**
     * Mod version is compatible with Fika.
     */
    case Compatible = 'compatible';

    /**
     * Mod version is not compatible with Fika.
     */
    case Incompatible = 'incompatible';

    /**
     * Fika compatibility status is unknown.
     */
    case Unknown = 'unknown';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Compatible => 'Fika Compatible',
            self::Incompatible => 'Fika Incompatible',
            self::Unknown => 'Fika Compatibility Unknown',
        };
    }

    /**
     * Get a human-readable label for the status when displayed at the mod level (details section).
     */
    public function modLabel(): string
    {
        return match ($this) {
            self::Compatible => 'Fika Compatible Version Available',
            self::Incompatible => 'Fika Incompatible',
            self::Unknown => 'Fika Compatibility Unknown',
        };
    }

    /**
     * Get the icon name for this status (for UI).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Compatible => 'check-circle',
            self::Incompatible => 'x-circle',
            self::Unknown => 'question-mark-circle',
        };
    }

    /**
     * Get the color class for this status (for UI).
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Compatible => 'text-green-600 dark:text-green-500',
            self::Incompatible => 'text-red-600 dark:text-red-500',
            self::Unknown => 'text-gray-600 dark:text-gray-400',
        };
    }

    /**
     * Get the badge-version modifier class name for this status.
     */
    public function badgeVersionClass(): string
    {
        return match ($this) {
            self::Compatible => 'green',
            self::Incompatible => 'orange',
            self::Unknown => 'gray',
        };
    }

    /**
     * Check if this status represents Fika compatibility.
     */
    public function isCompatible(): bool
    {
        return $this === self::Compatible;
    }
}
