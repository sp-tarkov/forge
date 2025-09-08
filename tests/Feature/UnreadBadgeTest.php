<?php

declare(strict_types=1);

use App\Livewire\NavigationChat;
use App\Livewire\Page\Chat;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows unread badge for other user when first message is sent', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // User1 starts a conversation with User2
    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

    // User1 sends a message
    Livewire::actingAs($user1)
        ->test(Chat::class, ['conversationHash' => $conversation->hash_id])
        ->set('messageText', 'Hello there!')
        ->call('sendMessage');

    // Check that User2 sees unread badge in navigation
    Livewire::actingAs($user2)
        ->test(NavigationChat::class)
        ->assertSee($user1->name)
        ->assertSee('1'); // Should see unread count badge

    // Verify unread count for user2
    expect($conversation->fresh()->getUnreadCountForUser($user2))->toBe(1);
});

it('shows correct unread count in navigation dropdown', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Create conversation
    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

    // User1 sends multiple messages
    $conversation->messages()->create([
        'user_id' => $user1->id,
        'content' => 'First message',
    ]);

    $conversation->messages()->create([
        'user_id' => $user1->id,
        'content' => 'Second message',
    ]);

    $conversation->messages()->create([
        'user_id' => $user1->id,
        'content' => 'Third message',
    ]);

    // User2's navigation should show unread count
    Livewire::actingAs($user2)
        ->test(NavigationChat::class)
        ->assertSee('3'); // Should see badge with count 3
});

it('shows unread badge on main navigation button', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    // Create two conversations with unread messages for user2
    $conv1 = Conversation::findOrCreateBetween($user1, $user2, $user1);
    $conv1->messages()->create([
        'user_id' => $user1->id,
        'content' => 'Message from user1',
    ]);

    $conv2 = Conversation::findOrCreateBetween($user3, $user2, $user3);
    $conv2->messages()->create([
        'user_id' => $user3->id,
        'content' => 'Message from user3',
    ]);

    // User2 should see total unread count on navigation button
    Livewire::actingAs($user2)
        ->test(NavigationChat::class)
        ->assertSee('2'); // Total unread conversations
});
