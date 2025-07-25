<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Comment;
use App\Models\CommentSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * @template TModel of Model
 */
interface Commentable
{
    /**
     * Get all comments for this commentable model.
     *
     * @return MorphMany<Comment, TModel>
     */
    public function comments(): MorphMany;

    /**
     * Get all root comments for this commentable model.
     *
     * @return MorphMany<Comment, TModel>
     */
    public function rootComments(): MorphMany;

    /**
     * Determine if this commentable model can receive comments. This should include all business logic for comment
     * permissions, including publication status, user preferences, moderation settings, etc.
     */
    public function canReceiveComments(): bool;

    /**
     * Get the display name for this commentable model.
     */
    public function getCommentableDisplayName(): string;

    /**
     * Get the title of this commentable for display in notifications and UI.
     */
    public function getTitle(): string;

    /**
     * Get the default subscribers for this commentable model.
     *
     * @return Collection<int, User>
     */
    public function getDefaultSubscribers(): Collection;

    /**
     * Get the URL to view this commentable model.
     */
    public function getCommentableUrl(): string;

    /**
     * Returns the hash for the tab that contains the comments on this commentable model. Returns null if these comments
     * are not contained within a tab.
     */
    public function getCommentTabHash(): ?string;

    /**
     * Get all subscribers for this commentable.
     *
     * @return Collection<int, User>
     */
    public function getSubscribers(): Collection;

    /**
     * Subscribe a user to comment notifications for this commentable.
     */
    public function subscribeUser(User $user): CommentSubscription;

    /**
     * Unsubscribe a user from comment notifications for this commentable.
     */
    public function unsubscribeUser(User $user): bool;

    /**
     * Check if a user is subscribed to comment notifications for this commentable.
     */
    public function isUserSubscribed(User $user): bool;

    /**
     * Ensure default subscribers are subscribed to this commentable.
     */
    public function ensureDefaultSubscriptions(): void;
}
