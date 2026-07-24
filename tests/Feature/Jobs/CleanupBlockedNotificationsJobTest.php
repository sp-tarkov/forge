<?php

declare(strict_types=1);

use App\Jobs\CleanupBlockedNotificationsJob;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\NewChatMessageNotification;
use App\Notifications\NewCommentNotification;
use Illuminate\Support\Str;

function createDatabaseNotification(User $recipient, string $type, array $data): void
{
    $recipient->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => $type,
        'data' => $data,
    ]);
}

it('removes the blocker notifications that originate from the blocked user', function (): void {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();

    createDatabaseNotification($blocker, CommentReplyNotification::class, ['commenter_id' => $blocked->id]);
    createDatabaseNotification($blocker, NewCommentNotification::class, ['commenter_id' => $blocked->id]);
    createDatabaseNotification($blocker, NewChatMessageNotification::class, ['sender_id' => $blocked->id]);

    new CleanupBlockedNotificationsJob($blocker, $blocked)->handle();

    expect($blocker->notifications()->count())->toBe(0);
});

it('keeps the blocker notifications from other users', function (): void {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();
    $otherUser = User::factory()->create();

    createDatabaseNotification($blocker, CommentReplyNotification::class, ['commenter_id' => $otherUser->id]);
    createDatabaseNotification($blocker, NewChatMessageNotification::class, ['sender_id' => $otherUser->id]);
    createDatabaseNotification($blocker, CommentReplyNotification::class, ['commenter_id' => $blocked->id]);

    new CleanupBlockedNotificationsJob($blocker, $blocked)->handle();

    expect($blocker->notifications()->count())->toBe(2);
});

it('does not touch the blocked user notifications', function (): void {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();

    createDatabaseNotification($blocked, CommentReplyNotification::class, ['commenter_id' => $blocker->id]);

    new CleanupBlockedNotificationsJob($blocker, $blocked)->handle();

    expect($blocked->notifications()->count())->toBe(1);
});
