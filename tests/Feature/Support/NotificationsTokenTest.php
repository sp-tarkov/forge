<?php

declare(strict_types=1);

use App\Events\UserNotificationsChanged;
use App\Models\User;
use App\Support\NotificationsToken;
use Illuminate\Support\Facades\Event;

it('returns a stable token across reads', function (): void {
    $user = User::factory()->create();

    $token = NotificationsToken::current($user->id);

    expect(NotificationsToken::current($user->id))->toBe($token);
});

it('mints a new token after forgetting', function (): void {
    $user = User::factory()->create();

    $token = NotificationsToken::current($user->id);

    NotificationsToken::forget($user->id);

    expect(NotificationsToken::current($user->id))->not->toBe($token);
});

it('scopes tokens per user', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $tokenA = NotificationsToken::current($userA->id);

    NotificationsToken::forget($userB->id);

    expect(NotificationsToken::current($userA->id))->toBe($tokenA);
});

it('mints a new token after flushing', function (): void {
    $user = User::factory()->create();

    $token = NotificationsToken::current($user->id);

    NotificationsToken::flush($user->id);

    expect(NotificationsToken::current($user->id))->not->toBe($token);
});

it('broadcasts the change when flushing', function (): void {
    $user = User::factory()->create();

    Event::fake([UserNotificationsChanged::class]);

    NotificationsToken::flush($user->id);

    Event::assertDispatched(UserNotificationsChanged::class, fn (UserNotificationsChanged $event): bool => $event->userId === $user->id);
});
