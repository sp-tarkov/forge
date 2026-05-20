<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

/**
 * Register a broadcast driver that always fails, mimicking an unreachable
 * websocket server (Nightwatch issue #30). The chat broadcast events are
 * queued, but on the sync queue used in tests they resolve inline, so this
 * exercises the worst case: the broadcaster throwing during the request.
 */
function useFailingBroadcaster(): void
{
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
}

it('does not 500 the chat page when the websocket server is unreachable', function (): void {
    useFailingBroadcaster();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

    Livewire::actingAs($user1)
        ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
        ->assertOk();
});

it('still sends and persists a message when broadcasting fails', function (): void {
    useFailingBroadcaster();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

    Livewire::actingAs($user1)
        ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
        ->assertOk()
        ->set('messageText', 'Message sent during a websocket outage')
        ->call('sendMessage')
        ->assertOk()
        ->assertSet('messageText', '')
        ->assertSee('Message sent during a websocket outage');

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'user_id' => $user1->id,
        'content' => 'Message sent during a websocket outage',
    ]);
});

it('does not crash typing or read broadcasts when broadcasting fails', function (): void {
    useFailingBroadcaster();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

    Livewire::actingAs($user1)
        ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
        ->assertOk()
        ->call('handleTyping')
        ->assertOk()
        ->call('stopTyping')
        ->assertOk();
});

it('logs a warning when a chat broadcast fails', function (): void {
    useFailingBroadcaster();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, 'Chat broadcast failed'));

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

    Livewire::actingAs($user1)
        ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
        ->assertOk()
        ->set('messageText', 'Triggers a failed broadcast')
        ->call('sendMessage')
        ->assertOk();
});
