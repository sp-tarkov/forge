<?php

declare(strict_types=1);

use App\Events\UserNotificationsChanged;
use App\Models\User;
use App\Support\NotificationsToken;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

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

it('logs a warning and still forgets the token when the flush broadcast fails', function (): void {
    $user = User::factory()->create();

    $token = NotificationsToken::current($user->id);

    Broadcast::extend('failing', fn (): Broadcaster => new class implements Broadcaster
    {
        public function auth($request): mixed
        {
            return true;
        }

        public function validAuthenticationResponse($request, $result): mixed
        {
            return $result;
        }

        /**
         * @param  array<int, string>  $channels
         * @param  array<string, mixed>  $payload
         */
        public function broadcast(array $channels, $event, array $payload = []): void
        {
            throw new BroadcastException('cURL error 7: Failed to connect to forge-ws.sp-tarkov.com port 80');
        }
    });

    config()->set('broadcasting.connections.failing', ['driver' => 'failing']);
    config()->set('broadcasting.default', 'failing');

    Log::spy();

    NotificationsToken::flush($user->id);

    expect(NotificationsToken::current($user->id))->not->toBe($token);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Notifications broadcast failed'));
});
