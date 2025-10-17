<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationType;
use Database\Factories\NotificationLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property int $user_id
 * @property NotificationType $notification_type
 * @property string $notification_class
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Model $notifiable
 */
class NotificationLog extends Model
{
    /** @use HasFactory<NotificationLogFactory> */
    use HasFactory;

    /**
     * Check if a notification has already been sent for a notifiable/user combination.
     */
    public static function hasBeenSent(
        Model $notifiable,
        int $userId,
        string $notificationClass
    ): bool {
        return self::query()->where('notifiable_type', $notifiable::class)
            ->where('notifiable_id', $notifiable->getKey())
            ->where('user_id', $userId)
            ->where('notification_class', $notificationClass)
            ->exists();
    }

    /**
     * Record that a notification has been sent.
     */
    public static function recordSent(
        Model $notifiable,
        int $userId,
        string $notificationClass,
        NotificationType $notificationType = NotificationType::ALL
    ): self {
        return self::query()->firstOrCreate(
            [
                'notifiable_type' => $notifiable::class,
                'notifiable_id' => $notifiable->getKey(),
                'user_id' => $userId,
                'notification_class' => $notificationClass,
            ],
            [
                'notification_type' => $notificationType,
            ]
        );
    }

    /**
     * Get the notifiable model (e.g., Comment, Post, etc.).
     *
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user that this notification was sent to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_type' => NotificationType::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
