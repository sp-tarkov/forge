<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case EMAIL = 'email';

    case DATABASE = 'database';

    case ALL = 'all';

    /**
     * Get all available values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get a human-readable label for the notification type.
     */
    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::DATABASE => 'Database',
            self::ALL => 'All Channels',
        };
    }
}
