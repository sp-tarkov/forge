<?php

declare(strict_types=1);

use App\Events\UserNotificationsChanged;
use App\Models\User;
use App\Notifications\NewCommentNotification;
use App\Support\NotificationsToken;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;

function createNotificationFor(User $user): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => NewCommentNotification::class,
        'data' => ['commenter_name' => 'Test User'],
        'read_at' => null,
    ]);
}

it('forgets the notifications token when a notification is created', function (): void {
    $user = User::factory()->create();

    $token = NotificationsToken::current($user->id);

    createNotificationFor($user);

    expect(NotificationsToken::current($user->id))->not->toBe($token);
});

it('forgets the notifications token when a notification is updated', function (): void {
    $user = User::factory()->create();
    $notification = createNotificationFor($user);

    $token = NotificationsToken::current($user->id);

    $notification->markAsRead();

    expect(NotificationsToken::current($user->id))->not->toBe($token);
});

it('forgets the notifications token when a notification is deleted', function (): void {
    $user = User::factory()->create();
    $notification = createNotificationFor($user);

    $token = NotificationsToken::current($user->id);

    $notification->delete();

    expect(NotificationsToken::current($user->id))->not->toBe($token);
});

it('broadcasts the change when a notification is created', function (): void {
    $user = User::factory()->create();

    Event::fake([UserNotificationsChanged::class]);

    createNotificationFor($user);

    Event::assertDispatched(UserNotificationsChanged::class, fn (UserNotificationsChanged $event): bool => $event->userId === $user->id);
});

it('broadcasts the change when a notification is updated', function (): void {
    $user = User::factory()->create();
    $notification = createNotificationFor($user);

    Event::fake([UserNotificationsChanged::class]);

    $notification->markAsRead();

    Event::assertDispatched(UserNotificationsChanged::class, fn (UserNotificationsChanged $event): bool => $event->userId === $user->id);
});
