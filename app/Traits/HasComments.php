<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Comment;
use App\Models\CommentSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * @template TModel of Model
 *
 * @mixin TModel
 */
trait HasComments
{
    /**
     * The relationship between a model and its comments.
     *
     * @return MorphMany<Comment, TModel>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * The relationship between a model and its root comments.
     *
     * @return MorphMany<Comment, TModel>
     */
    public function rootComments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')
            ->whereNull('parent_id')
            ->whereNull('root_id')
            ->with(['user', 'descendants', 'descendants.user', 'descendants.reactions', 'reactions'])
            ->orderByRaw('pinned_at IS NULL, pinned_at DESC')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get all comment subscriptions for this commentable.
     *
     * @return MorphMany<CommentSubscription, TModel>
     */
    public function commentSubscriptions(): MorphMany
    {
        return $this->morphMany(CommentSubscription::class, 'commentable');
    }

    /**
     * Get subscribers for this commentable.
     *
     * @return Collection<int, User>
     */
    public function getSubscribers(): Collection
    {
        /** @var Collection<int, User> $users */
        $users = $this->commentSubscriptions()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        return $users;
    }

    /**
     * Subscribe a user to comment notifications for this commentable.
     */
    public function subscribeUser(User $user): CommentSubscription
    {
        return CommentSubscription::subscribe($user, $this);
    }

    /**
     * Unsubscribe a user from comment notifications for this commentable.
     */
    public function unsubscribeUser(User $user): bool
    {
        return CommentSubscription::unsubscribe($user, $this);
    }

    /**
     * Check if a user is subscribed to comment notifications for this commentable.
     */
    public function isUserSubscribed(User $user): bool
    {
        return CommentSubscription::isSubscribed($user, $this);
    }

    /**
     * Ensure default subscribers are subscribed to this commentable.
     */
    public function ensureDefaultSubscriptions(): void
    {
        $defaultSubscribers = $this->getDefaultSubscribers();

        foreach ($defaultSubscribers as $user) {
            $this->subscribeUser($user);
        }
    }
}
