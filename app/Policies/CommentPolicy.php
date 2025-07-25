<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Commentable;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Comment $comment): bool
    {
        // Clean comments are visible to everyone
        if ($comment->isSpamClean()) {
            return true;
        }

        // If not logged in, can only see clean comments
        if ($user === null) {
            return false;
        }

        // Moderators and admins can see all comments
        if ($user->isModOrAdmin()) {
            return true;
        }

        // Comment authors can see their own comments regardless of spam status
        if ($comment->user_id === $user->id) {
            return true;
        }

        // Everyone else cannot see spam/pending comments
        return false;
    }

    /**
     * Determine whether the user can create a comment.
     *
     * - Must be logged in. Handled by not null User parameter.
     * - The commentable must allow comments.
     *
     * @param  Commentable<Mod|User>|null  $commentable>
     */
    public function create(User $user, ?Commentable $commentable = null): bool
    {
        // TODO: Users who are blocked by mod authors can not comment on that author's mods.

        // Commentable is required
        if ($commentable === null) {
            return false;
        }

        // Check if the commentable can receive comments
        return $commentable->canReceiveComments();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Comment $comment): bool
    {
        if ($user->isModOrAdmin()) {
            return true;
        }

        // The user can update the comment if they are the author of the comment.
        if ($user->id !== $comment->user_id) {
            return false;
        }

        // The user can update the comment if it is not older than the configured time limit.
        $editTimeLimit = config('comments.editing.edit_time_limit_minutes', 5);
        if ($comment->created_at->diffInMinutes(now()) > $editTimeLimit) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     * Users can only delete their own comments.
     */
    public function delete(User $user, Comment $comment): bool
    {
        // Comment must not yet be deleted
        if ($comment->isDeleted()) {
            return false;
        }

        // Only the author can delete their own comment
        return $user->id === $comment->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Comment $comment): bool
    {
        // Comment must be soft-deleted to be restored
        if (! $comment->isDeleted()) {
            return false;
        }

        // Must be moderator or admin
        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Comment $comment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can react to the comment.
     *
     * - Must be logged in. Handled by not null User parameter.
     * - The comment must exist. Handled by not null Comment parameter.
     * - The user must not be the author of the comment.
     */
    public function react(User $user, Comment $comment): bool
    {
        // The user must not be the author of the comment.
        if ($user->id === $comment->user_id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can see the spam status ribbon for a comment.
     *
     * Ribbons are shown to moderators/admins when:
     * - The comment is not clean (spam or pending), AND
     * - The comment is spam (always shown to mods/admins), OR
     * - The current user is not the comment author (show pending to mods/admins who didn't write it)
     */
    public function seeRibbon(?User $user, Comment $comment): bool
    {
        // Must be logged in
        if ($user === null) {
            return false;
        }

        // Must be moderator or admin
        if (! $user->isModOrAdmin()) {
            return false;
        }

        // Only show ribbons for non-clean comments
        if ($comment->isSpamClean()) {
            return false;
        }

        // Always show spam ribbons to mods/admins
        if ($comment->isSpam()) {
            return true;
        }

        // Show pending ribbons to mods/admins who are not the comment author
        return $comment->user_id !== $user->id;
    }

    /**
     * Determine whether the user can view moderation actions for the comment.
     */
    public function viewActions(?User $user, Comment $comment): bool
    {
        // Must be logged in
        if ($user === null) {
            return false;
        }

        // Must be moderator or admin
        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can soft-delete the comment.
     */
    public function softDelete(User $user, Comment $comment): bool
    {
        // Comment must not yet be deleted
        if ($comment->isDeleted()) {
            return false;
        }

        // Must be moderator or admin
        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can hard delete the comment thread.
     */
    public function hardDelete(User $user, Comment $comment): bool
    {
        // Comment must not yet be deleted
        if ($comment->isDeleted()) {
            return false;
        }

        // Must be administrator
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can mark the comment as spam.
     */
    public function markAsSpam(User $user, Comment $comment): bool
    {
        // Comment must not yet be marked as spam
        if ($comment->isSpam()) {
            return false;
        }

        // Must be moderator or admin
        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can mark the comment as ham (not spam).
     */
    public function markAsHam(User $user, Comment $comment): bool
    {
        // Comment must be marked as spam to mark as ham
        if (! $comment->isSpam()) {
            return false;
        }

        // Must be moderator or admin
        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can check the comment for spam using Akismet.
     */
    public function checkForSpam(User $user, Comment $comment): bool
    {
        // Must be moderator or admin
        if (! $user->isModOrAdmin()) {
            return false;
        }

        // Can only check if we haven't exceeded max attempts
        return $comment->canBeRechecked();
    }
}
