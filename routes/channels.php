<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Private channel for conversation messages
 */
Broadcast::channel('conversation.{conversationHashId}', function (User $user, string $conversationHashId): bool {
    $conversation = Conversation::query()->where('hash_id', $conversationHashId)->first();

    return $conversation !== null && $conversation->hasUser($user);
});

/*
 * Private channel for user notifications
 */
Broadcast::channel('user.{id}', fn (User $user, string $id): bool => $user->id === (int) $id);

/*
 * Presence channel for tracking online users globally
 */
Broadcast::channel('presence.online', fn (User $user): array => [
    'id' => $user->id,
    'name' => $user->name,
    'profile_photo_url' => $user->profile_photo_url,
]);

/*
 * Presence channel for tracking users in a conversation (typing indicators)
 */
Broadcast::channel('presence.conversation.{conversationHashId}', function (User $user, string $conversationHashId): ?array {
    $conversation = Conversation::query()->where('hash_id', $conversationHashId)->first();

    if ($conversation !== null && $conversation->hasUser($user)) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'profile_photo_url' => $user->profile_photo_url,
        ];
    }

    return null;
});
