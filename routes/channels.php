<?php

declare(strict_types=1);

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
 * A private broadcast "presents" channel that we've allowed unauthorized users to join by assigning a temporary Guest
 * model as their user state. Guest users are identified by a hashed version of their session ID.
 */
Broadcast::channel('visitors', function ($user) {
    // For guest users, the ID is already hashed in VisitorsPresenceBroadcastingController
    // For authenticated users, we use their actual ID
    $userId = isset($user->is_guest) && $user->is_guest
        ? $user->id  // Already hashed for guests
        : (string) $user->id;  // Actual ID for authenticated users

    return [
        'id' => $userId,
        'type' => isset($user->is_guest) && $user->is_guest ? 'guest' : 'authenticated',
    ];
});

/*
 * Private channel for conversation messages
 */
Broadcast::channel('conversation.{conversationHashId}', function ($user, $conversationHashId) {
    $conversation = Conversation::query()->where('hash_id', $conversationHashId)->first();

    return $conversation && $conversation->hasUser($user);
});

/*
 * Private channel for user notifications
 */
Broadcast::channel('user.{id}', fn ($user, $id): bool => (int) $user->id === (int) $id);

/*
 * Presence channel for tracking online users globally
 */
Broadcast::channel('presence.online', function ($user) {
    if ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'profile_photo_url' => $user->profile_photo_url,
        ];
    }
});

/*
 * Presence channel for tracking users in a conversation (typing indicators)
 */
Broadcast::channel('presence.conversation.{conversationHashId}', function ($user, $conversationHashId) {
    $conversation = Conversation::query()->where('hash_id', $conversationHashId)->first();

    if ($conversation && $conversation->hasUser($user)) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'profile_photo_url' => $user->profile_photo_url,
        ];
    }
});
