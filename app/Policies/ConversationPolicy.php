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
        // User can send messages if they are part of the conversation
        // (even if they can't see it yet - this allows the non-creator to send the first message)
        return $conversation->hasUser($user);
    }
}
