<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the visibility level of a user-curated mod list.
 */
enum ListVisibility: string
{
    /**
     * The list is searchable, listed on the owner's profile, and available in the API.
     */
    case Public = 'public';

    /**
     * The list is accessible only via a share link. Not listed or indexed.
     */
    case Hidden = 'hidden';

    /**
     * The list is accessible only to the owner.
     */
    case Private = 'private';

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Hidden => 'Hidden',
            self::Private => 'Private',
        };
    }

    /**
     * Get a one-line description suitable for form help text.
     */
    public function description(): string
    {
        return match ($this) {
            self::Public => 'Searchable and shown on your profile',
            self::Hidden => 'Only people with the share link can view it',
            self::Private => 'Only you can view it',
        };
    }

    /**
     * Get the heroicon name for this visibility.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Public => 'globe-alt',
            self::Hidden => 'link',
            self::Private => 'lock-closed',
        };
    }

    /**
     * Whether the list requires a share token to be accessible to non-owners.
     */
    public function requiresShareToken(): bool
    {
        return $this === self::Hidden;
    }
}
