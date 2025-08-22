<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Commentable;
use Database\Factories\CommentSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $commentable_id
 * @property string $commentable_type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Model $commentable
 * @property User $user
 */
class CommentSubscription extends Model
{
    /** @use HasFactory<CommentSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'commentable_type',
        'commentable_id',
    ];

    /**
     * Get the user that owns the subscription.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the commentable model (Mod or User).
     *
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create a subscription for a user to a commentable.
     *
     * @template T of Model
     *
     * @param  T&Commentable<T>  $commentable
     */
    public static function subscribe(User $user, Model&Commentable $commentable): self
    {
        return self::query()->firstOrCreate([
            'user_id' => $user->id,
            'commentable_type' => $commentable::class,
            'commentable_id' => $commentable->getAttribute('id'),
        ]);
    }

    /**
     * Remove a subscription for a user from a commentable.
     *
     * @template T of Model
     *
     * @param  T&Commentable<T>  $commentable
     */
    public static function unsubscribe(User $user, Model&Commentable $commentable): bool
    {
        return self::query()->where([
            'user_id' => $user->id,
            'commentable_type' => $commentable::class,
            'commentable_id' => $commentable->getAttribute('id'),
        ])->delete() > 0;
    }

    /**
     * Check if a user is subscribed to a commentable.
     *
     * @template T of Model
     *
     * @param  T&Commentable<T>  $commentable
     */
    public static function isSubscribed(User $user, Model&Commentable $commentable): bool
    {
        return self::query()->where([
            'user_id' => $user->id,
            'commentable_type' => $commentable::class,
            'commentable_id' => $commentable->getAttribute('id'),
        ])->exists();
    }
}
