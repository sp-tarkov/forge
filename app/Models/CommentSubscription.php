<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Commentable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommentSubscription extends Model
{
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
     * @param  Commentable<Model>  $commentable
     */
    public static function subscribe(User $user, Commentable $commentable): self
    {
        return self::query()->firstOrCreate([
            'user_id' => $user->id,
            'commentable_type' => $commentable::class,
            'commentable_id' => $commentable->getId(),
        ]);
    }

    /**
     * Remove a subscription for a user from a commentable.
     *
     * @param  Commentable<Model>  $commentable
     */
    public static function unsubscribe(User $user, Commentable $commentable): bool
    {
        return self::query()->where([
            'user_id' => $user->id,
            'commentable_type' => $commentable::class,
            'commentable_id' => $commentable->getId(),
        ])->delete() > 0;
    }

    /**
     * Check if a user is subscribed to a commentable.
     *
     * @param  Commentable<Model>  $commentable
     */
    public static function isSubscribed(User $user, Commentable $commentable): bool
    {
        return self::query()->where([
            'user_id' => $user->id,
            'commentable_type' => $commentable::class,
            'commentable_id' => $commentable->getId(),
        ])->exists();
    }
}
