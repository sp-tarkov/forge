<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Comment;
use App\Models\CommentSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;

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
            ->whereNull(['parent_id', 'root_id'])
            ->with([
                'user:id,name,email,user_role_id,profile_photo_path,created_at',
                'user.role:id,name',
                'reactions:id,comment_id,user_id',
                'parent:id,user_id',
                'parent.user:id,name',
            ])
            ->withCount('reactions')
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
     * @return SupportCollection<int, User>
     */
    public function getSubscribers(): SupportCollection
    {
        /** @var SupportCollection<int, User> $users */
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

    /**
     * Load descendants for a specific comment.
     *
     * @return Collection<int, Comment>
     */
    public function loadDescendants(Comment $comment, ?User $user = null): Collection
    {
        if ($user === null) {
            $user = Auth::user();
        }

        $query = $comment->descendants()
            ->with([
                'user:id,name,email,user_role_id,profile_photo_path,created_at',
                'user.role:id,name',
                'reactions:id,comment_id,user_id',
                'parent:id,user_id',
                'parent.user:id,name',
            ])
            ->withCount('reactions')
            ->visibleToUser($user)
            ->orderBy('created_at');

        return $query->get();
    }

    /**
     * Get descendant counts for root comments.
     *
     * @return array<int, int>
     */
    public function getDescendantCounts(): array
    {
        $user = Auth::user();
        
        return Comment::query()
            ->selectRaw('root_id, count(*) as reply_count')
            ->where('commentable_type', static::class)
            ->where('commentable_id', $this->getKey())
            ->whereNotNull('root_id')
            ->visibleToUser($user)
            ->groupBy(['root_id'])
            ->pluck('reply_count', 'root_id')
            ->toArray();
    }
}
