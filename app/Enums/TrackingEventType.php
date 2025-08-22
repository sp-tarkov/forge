<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;

enum TrackingEventType: string
{
    /** User authentication events */
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case REGISTER = 'register';
    case PASSWORD_CHANGE = 'password_change';

    /** Mod-related events - requires a Mod model */
    case MOD_DOWNLOAD = 'mod_download';
    case MOD_CREATE = 'mod_create';
    case MOD_EDIT = 'mod_edit';
    case MOD_DELETE = 'mod_delete';
    case MOD_REPORT = 'mod_report';

    /** Mod version events - requires a ModVersion model */
    case VERSION_CREATE = 'version_create';
    case VERSION_EDIT = 'version_edit';
    case VERSION_DELETE = 'version_delete';

    /** Comment events - requires a Comment model */
    case COMMENT_CREATE = 'comment_create';
    case COMMENT_EDIT = 'comment_edit';
    case COMMENT_DELETE = 'comment_delete';
    case COMMENT_LIKE = 'comment_like';
    case COMMENT_UNLIKE = 'comment_unlike';
    case COMMENT_REPORT = 'comment_report';

    /** Account management events */
    case ACCOUNT_DELETE = 'account_delete';

    /**
     * Get the user-friendly display name for this event type.
     */
    public function getName(): string
    {
        return match ($this) {
            self::LOGIN => 'Logged in',
            self::LOGOUT => 'Logged out',
            self::REGISTER => 'Registered account',
            self::PASSWORD_CHANGE => 'Changed password',
            self::MOD_DOWNLOAD => 'Downloaded mod',
            self::MOD_CREATE => 'Created mod',
            self::MOD_EDIT => 'Edited mod',
            self::MOD_DELETE => 'Deleted mod',
            self::VERSION_CREATE => 'Created mod version',
            self::VERSION_EDIT => 'Edited mod version',
            self::VERSION_DELETE => 'Deleted mod version',
            self::COMMENT_CREATE => 'Created comment',
            self::COMMENT_EDIT => 'Edited comment',
            self::COMMENT_DELETE => 'Deleted comment',
            self::COMMENT_LIKE => 'Liked comment',
            self::COMMENT_UNLIKE => 'Unliked comment',
            self::COMMENT_REPORT => 'Reported comment',
            self::MOD_REPORT => 'Reported mod',
            self::ACCOUNT_DELETE => 'Deleted account',
        };
    }

    /**
     * Get a detailed description of what this event represents.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::LOGIN => 'User successfully logged in',
            self::LOGOUT => 'User logged out',
            self::REGISTER => 'User created a new account',
            self::PASSWORD_CHANGE => 'User changed their password',
            self::MOD_DOWNLOAD => 'User downloaded a mod',
            self::MOD_CREATE => 'User created a new mod',
            self::MOD_EDIT => 'User edited a mod',
            self::MOD_DELETE => 'User deleted a mod',
            self::VERSION_CREATE => 'User created a new mod version',
            self::VERSION_EDIT => 'User edited a mod version',
            self::VERSION_DELETE => 'User deleted a mod version',
            self::COMMENT_CREATE => 'User created a comment',
            self::COMMENT_EDIT => 'User edited a comment',
            self::COMMENT_DELETE => 'User deleted a comment',
            self::COMMENT_LIKE => 'User liked a comment',
            self::COMMENT_UNLIKE => 'User unliked a comment',
            self::COMMENT_REPORT => 'User reported a comment',
            self::MOD_REPORT => 'User reported a mod',
            self::ACCOUNT_DELETE => 'User deleted their account',
        };
    }

    /**
     * Get the fully qualified class name of the model this event can track.
     */
    public function getTrackableModel(): ?string
    {
        return match ($this) {
            self::MOD_CREATE, self::MOD_EDIT, self::MOD_DELETE, self::MOD_REPORT => Mod::class,
            self::MOD_DOWNLOAD, self::VERSION_CREATE, self::VERSION_EDIT, self::VERSION_DELETE => ModVersion::class,
            self::COMMENT_CREATE, self::COMMENT_EDIT, self::COMMENT_DELETE, self::COMMENT_LIKE, self::COMMENT_UNLIKE, self::COMMENT_REPORT => Comment::class,
            default => null,
        };
    }

    /**
     * Determine if this event type requires a trackable model instance.
     *
     * Returns true if the event should be associated with a specific model instance (e.g., downloading a specific mod),
     * false if it's a general event that doesn't relate to a specific model (e.g., user login).
     */
    public function requiresTrackable(): bool
    {
        return $this->getTrackableModel() !== null;
    }

    /**
     * Get the Flux UI icon name for this event type.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::LOGIN => 'arrow-right-end-on-rectangle',
            self::LOGOUT => 'arrow-left-start-on-rectangle',
            self::REGISTER => 'user-plus',
            self::PASSWORD_CHANGE => 'key',
            self::MOD_DOWNLOAD => 'arrow-down-tray',
            self::MOD_CREATE => 'plus-circle',
            self::MOD_EDIT => 'pencil-square',
            self::MOD_DELETE => 'trash',
            self::MOD_REPORT => 'flag',
            self::VERSION_CREATE => 'tag',
            self::VERSION_EDIT => 'pencil',
            self::VERSION_DELETE => 'x-circle',
            self::COMMENT_CREATE => 'chat-bubble-left',
            self::COMMENT_EDIT => 'chat-bubble-left-ellipsis',
            self::COMMENT_DELETE => 'chat-bubble-left-right',
            self::COMMENT_LIKE => 'heart',
            self::COMMENT_UNLIKE => 'heart',
            self::COMMENT_REPORT => 'exclamation-triangle',
            self::ACCOUNT_DELETE => 'user-minus',
        };
    }

    /**
     * Get the Flux UI badge color for this event type.
     */
    public function getColor(): string
    {
        return match ($this) {
            // Authentication events - Blue/Cyan theme
            self::LOGIN => 'blue',
            self::LOGOUT => 'cyan',
            self::REGISTER => 'green',
            self::PASSWORD_CHANGE => 'indigo',

            // Mod events - Purple/Violet theme
            self::MOD_DOWNLOAD => 'purple',
            self::MOD_CREATE => 'violet',
            self::MOD_EDIT => 'indigo',
            self::MOD_DELETE => 'red',
            self::MOD_REPORT => 'orange',

            // Version events - Teal/Emerald theme
            self::VERSION_CREATE => 'teal',
            self::VERSION_EDIT => 'emerald',
            self::VERSION_DELETE => 'red',

            // Comment events - Yellow/Amber theme
            self::COMMENT_CREATE => 'yellow',
            self::COMMENT_EDIT => 'amber',
            self::COMMENT_DELETE => 'red',
            self::COMMENT_LIKE => 'pink',
            self::COMMENT_UNLIKE => 'zinc',
            self::COMMENT_REPORT => 'orange',

            // Account management - Rose theme
            self::ACCOUNT_DELETE => 'rose',
        };
    }

    /**
     * Determine if this event type should show a URL/link.
     */
    public function shouldShowUrl(): bool
    {
        return match ($this) {
            self::LOGIN, self::LOGOUT, self::REGISTER, self::PASSWORD_CHANGE => false,
            default => true,
        };
    }

    /**
     * Determine if this event type should show context text.
     */
    public function shouldShowContext(): bool
    {
        return match ($this) {
            self::LOGIN, self::LOGOUT, self::REGISTER, self::PASSWORD_CHANGE => false,
            default => true,
        };
    }

    /**
     * Determine if this event type should be private (not shown to other users).
     * Private events are only visible to the user themselves, moderators, and administrators.
     */
    public function isPrivate(): bool
    {
        return match ($this) {
            self::LOGIN, self::LOGOUT, self::REGISTER, self::PASSWORD_CHANGE, self::ACCOUNT_DELETE, self::MOD_REPORT, self::COMMENT_REPORT => true,
            default => false,
        };
    }
}
