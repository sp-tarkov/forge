<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Any authenticated user can view their own conversations list
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        // Check if conversation is archived
        if ($conversation->isArchivedFor($user)) {
            return false;
        }

        // Use the visibility logic: creator can see even without messages,
        // other participant needs at least one message
        return $conversation->isVisibleTo($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can start a conversation
        return true;
    }

    /**
     * Determine whether the user can create a conversation with a specific user.
     */
    public function createWithUser(User $user, User $target): bool
    {
        // Cannot create conversation if blocked
        return ! $user->isBlockedMutually($target);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        // Users can update conversations they are part of (e.g., mark as read)
        return $conversation->hasUser($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        // Users can delete conversations they are part of
        return $conversation->hasUser($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Conversation $conversation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Conversation $conversation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can send messages to the conversation.
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        // Check if users have blocked each other
        $otherUser = $conversation->getOtherUser($user);
        if ($otherUser && $user->isBlockedMutually($otherUser)) {
            return false;
        }

        // User can send messages if they are part of the conversation
        // (even if they can't see it yet - this allows the non-creator to send the first message)
        return $conversation->hasUser($user);
    }

    /**
     * Determine whether the user can unarchive the conversation.
     */
    public function unarchive(User $user, Conversation $conversation): bool
    {
        // User must be part of the conversation
        if (! $conversation->hasUser($user)) {
            return false;
        }

        $otherUser = $conversation->getOtherUser($user);
        if ($otherUser === null) {
            return true;
        }

        // Allow unarchiving if:
        // 1. No blocking at all
        // 2. User is the blocker (can unarchive conversations with users they blocked)
        // But prevent if BOTH users block each other
        if ($user->hasBlocked($otherUser) && $user->isBlockedBy($otherUser)) {
            // Mutual blocking - cannot unarchive
            return false;
        }

        // Also prevent if only the other user blocks us (we can't interact with them)
        if (! $user->hasBlocked($otherUser) && $user->isBlockedBy($otherUser)) {
            return false;
        }

        return true;
    }
}
