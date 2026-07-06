<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationColorRole: string
{
    case Red = 'red';

    case Purple = 'purple';

    case Amber = 'amber';

    case Blue = 'blue';

    case Gray = 'gray';

    /**
     * Tailwind classes for a tinted icon background (dashboard density).
     */
    public function tailwindBgTint(): string
    {
        return match ($this) {
            self::Red => 'bg-red-900/30',
            self::Purple => 'bg-purple-900/30',
            self::Amber => 'bg-amber-900/30',
            self::Blue => 'bg-blue-900/30',
            self::Gray => 'bg-gray-800',
        };
    }

    /**
     * Tailwind classes for a solid icon background (nav density).
     */
    public function tailwindBgSolid(): string
    {
        return match ($this) {
            self::Red => 'bg-red-500',
            self::Purple => 'bg-purple-500',
            self::Amber => 'bg-amber-500',
            self::Blue => 'bg-blue-500',
            self::Gray => 'bg-gray-500',
        };
    }

    /**
     * Tailwind classes for accent text and tinted icon foreground.
     */
    public function tailwindAccentText(): string
    {
        return match ($this) {
            self::Red => 'text-red-400',
            self::Purple => 'text-purple-400',
            self::Amber => 'text-amber-400',
            self::Blue => 'text-blue-400',
            self::Gray => 'text-gray-400',
        };
    }
}
