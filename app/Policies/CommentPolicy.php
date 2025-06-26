<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
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
    public function view(User $user, Comment $comment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create a comment.
     *
     * - Must be logged in. Handled by not null User parameter.
     * - The comment must exist. Handled by not null Comment parameter.
     */
    public function create(User $user): bool
    {
        // TODO: Users who are blocked by mod authors can not comment on that author's mods.

        return true;
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

        // The user can update the comment if it is not older than 5 minutes.
        if ($comment->created_at->diffInMinutes(now()) > 5) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Comment $comment): bool
    {
        return false;
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
}
