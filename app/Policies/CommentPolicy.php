<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Commentable;
use App\Models\Addon;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

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
        // Check if comments are disabled for mods first
        if ($comment->commentable_type === Mod::class) {
            /** @var Mod $mod */
            $mod = $comment->commentable;
            if ($mod->comments_disabled) {
                // Guest users cannot view disabled comments
                if ($user === null) {
                    return false;
                }

                // Moderators and admins can view disabled comments
                if ($user->isModOrAdmin()) {
                    return true;
                }

                // Mod owners can view their mod's comments
                if ($mod->owner_id === $user->id) {
                    return true;
                }

                // Mod authors can view comments on their authored mods
                if ($mod->additionalAuthors->contains($user)) {
                    return true;
                }

                // All other users cannot view disabled comments
                return false;
            }
        }

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
     * - Must have verified email address.
     * - The commentable must allow comments.
     *
     * @param  Commentable<Mod|User>|null  $commentable>
     */
    public function create(User $user, ?Commentable $commentable = null): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        // Commentable is required
        if ($commentable === null) {
            return false;
        }

        // Check blocking for user profile comments
        if ($commentable instanceof User) {
            if ($user->isBlockedMutually($commentable)) {
                return false;
            }
        }

        // Check blocking for mod comments
        if ($commentable instanceof Mod) {
            /** @var User|null $owner */
            $owner = $commentable->owner;
            if ($owner !== null && $user->isBlockedMutually($owner)) {
                return false;
            }
        }

        // Check if the commentable can receive comments
        return $commentable->canReceiveComments();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Comment $comment): bool
    {
        // Only the comment author can edit their own comment
        return $user->id === $comment->user_id;
    }

    /**
     * Determine whether the user can view the comment's version history.
     */
    public function viewVersionHistory(?User $user, Comment $comment): bool
    {
        if ($user === null) {
            return false;
        }

        // Author can view own history
        if ($user->id === $comment->user_id) {
            return true;
        }

        // Mods, senior mods, admins can view all
        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can delete the model.
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
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

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
     */
    public function react(User $user, Comment $comment): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        // The user must not be the author of the comment.
        if ($user->id === $comment->user_id) {
            return false;
        }

        // Check if the user is blocked by the comment author
        if ($user->isBlockedMutually($comment->user)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can see the spam status ribbon for a comment.
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

        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        // Moderators and admins can always view actions
        if ($user->isModOrAdmin()) {
            return true;
        }

        // For mod comments, check if the user is an author or the owner
        if ($comment->commentable_type === Mod::class) {
            /** @var Mod $mod */
            $mod = $comment->commentable;

            // Check if the user is the mod owner
            if ($mod->owner_id === $user->id) {
                return true;
            }

            // Check if the user is one of the mod authors
            if ($mod->additionalAuthors->contains($user)) {
                return true;
            }
        }

        // For addon comments, check if the user is an author or the owner
        if ($comment->commentable_type === Addon::class) {
            /** @var Addon $addon */
            $addon = $comment->commentable;

            // Check if the user is the addon owner
            if ($addon->owner_id === $user->id) {
                return true;
            }

            // Check if the user is one of the addon authors
            if ($addon->additionalAuthors->contains($user)) {
                return true;
            }
        }

        // For user profile comments, check if the user owns the profile
        if ($comment->commentable_type === User::class) {
            /** @var User $profileUser */
            $profileUser = $comment->commentable;

            // Check if the user owns the profile being commented on
            if ($profileUser->id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can soft-delete the comment.
     */
    public function softDelete(User $user, Comment $comment): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        // Comment must not yet be deleted
        if ($comment->isDeleted()) {
            return false;
        }

        // Must be moderator or admin
        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the mod owner/author or profile owner can soft-delete the comment.
     */
    public function modOwnerSoftDelete(User $user, Comment $comment): bool
    {
        // Comment must not yet be deleted
        if ($comment->isDeleted()) {
            return false;
        }

        // Cannot delete comments made by administrators or moderators
        if ($comment->user->isModOrAdmin()) {
            return false;
        }

        // For mod comments, check if the user is an author or the owner
        if ($comment->commentable_type === Mod::class) {
            /** @var Mod $mod */
            $mod = $comment->commentable;

            // Check if the user is the mod owner
            if ($mod->owner_id === $user->id) {
                return true;
            }

            // Check if the user is one of the mod authors
            if ($mod->additionalAuthors->contains($user)) {
                return true;
            }
        }

        // For addon comments, check if the user is an author or the owner
        if ($comment->commentable_type === Addon::class) {
            /** @var Addon $addon */
            $addon = $comment->commentable;

            // Check if the user is the addon owner
            if ($addon->owner_id === $user->id) {
                return true;
            }

            // Check if the user is one of the addon authors
            if ($addon->additionalAuthors->contains($user)) {
                return true;
            }
        }

        // For user profile comments, check if the user owns the profile
        if ($comment->commentable_type === User::class) {
            /** @var User $profileUser */
            $profileUser = $comment->commentable;

            // Check if the user owns the profile being commented on
            if ($profileUser->id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the mod owner/author or profile owner can restore the comment.
     */
    public function modOwnerRestore(User $user, Comment $comment): bool
    {
        // Comment must be soft-deleted to be restored
        if (! $comment->isDeleted()) {
            return false;
        }

        // Cannot restore comments made by administrators or moderators
        if ($comment->user->isModOrAdmin()) {
            return false;
        }

        // For mod comments, check if the user is an author or the owner
        if ($comment->commentable_type === Mod::class) {
            /** @var Mod $mod */
            $mod = $comment->commentable;

            // Check if the user is the mod owner
            if ($mod->owner_id === $user->id) {
                return true;
            }

            // Check if the user is one of the mod authors
            if ($mod->additionalAuthors->contains($user)) {
                return true;
            }
        }

        // For addon comments, check if the user is an author or the owner
        if ($comment->commentable_type === Addon::class) {
            /** @var Addon $addon */
            $addon = $comment->commentable;

            // Check if the user is the addon owner
            if ($addon->owner_id === $user->id) {
                return true;
            }

            // Check if the user is one of the addon authors
            if ($addon->additionalAuthors->contains($user)) {
                return true;
            }
        }

        // For user profile comments, check if the user owns the profile
        if ($comment->commentable_type === User::class) {
            /** @var User $profileUser */
            $profileUser = $comment->commentable;

            // Check if the user owns the profile being commented on
            if ($profileUser->id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can hard delete the comment thread.
     */
    public function hardDelete(User $user, Comment $comment): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
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
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

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
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

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
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        // Must be moderator or admin
        if (! $user->isModOrAdmin()) {
            return false;
        }

        // Can only check if we haven't exceeded max attempts
        return $comment->canBeRechecked();
    }

    /**
     * Determine whether the user can pin or unpin the comment.
     */
    public function pin(User $user, Comment $comment): bool
    {
        // Only root comments can be pinned
        if (! $comment->isRoot()) {
            return false;
        }

        // Moderators and admins can always pin/unpin
        if ($user->isModOrAdmin()) {
            return true;
        }

        // For mod comments, check if the user is an author or the owner
        if ($comment->commentable_type === Mod::class) {
            /** @var Mod $mod */
            $mod = $comment->commentable;

            // Check if the user is the mod owner
            if ($mod->owner_id === $user->id) {
                return true;
            }

            // Check if the user is one of the mod authors
            if ($mod->additionalAuthors->contains($user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user should see the pin action in the comment actions (for mod authors/owners).
     */
    public function showOwnerPinAction(User $user, Comment $comment): bool
    {
        // Only root comments can be pinned
        if (! $comment->isRoot()) {
            return false;
        }

        // Don't show to moderators/admins (they use the moderation dropdown)
        if ($user->isModOrAdmin()) {
            return false;
        }

        // For mod comments, check if the user is an author or the owner
        if ($comment->commentable_type === Mod::class) {
            /** @var Mod $mod */
            $mod = $comment->commentable;

            // Check if the user is the mod owner
            if ($mod->owner_id === $user->id) {
                return true;
            }

            // Check if the user is one of the mod authors
            if ($mod->additionalAuthors->contains($user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can report a comment.
     */
    public function report(User $user, Model $reportable): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        // Moderators and administrators cannot create reports.
        if ($user->isModOrAdmin()) {
            return false;
        }

        // Users cannot report their own comments
        if ($reportable instanceof Comment && $reportable->user_id === $user->id) {
            return false;
        }

        // Check if the reportable model has the required method.
        if (! method_exists($reportable, 'hasBeenReportedBy')) {
            return false;
        }

        // User cannot report the same item more than once.
        return ! $reportable->hasBeenReportedBy($user->id);
    }
}
