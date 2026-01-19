<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows conversation to other user after first message is sent', function (): void {
    $user1 = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);

    // User1 creates conversation but no message yet
    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

    // At this point, conversation should NOT be visible to user2 (no messages)
    expect(Conversation::visibleTo($user2)->count())->toBe(0);

    // User1 sends the first message
    $message = $conversation->messages()->create([
        'user_id' => $user1->id,
        'content' => 'Hello Bob!',
    ]);

    // Now conversation should be visible to user2
    expect(Conversation::visibleTo($user2)->count())->toBe(1);

    // Verify last_message_id was set
    $conversation->refresh();
    expect($conversation->last_message_id)->toBe($message->id);
    expect($conversation->last_message_at)->not->toBeNull();

    // User2 should see the conversation in navigation with unread badge
    Livewire::actingAs($user2)
        ->test('navigation-chat')
        ->assertSee('Alice')  // Should see user1's name
        ->assertSee('1');      // Should see unread count
});

it('correctly tracks unread count after first message via Chat component', function (): void {
    $user1 = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);

    // User1 starts conversation and sends message through Chat component
    // Start conversation through NavigationChat or Chat component
    Livewire::actingAs($user1)
        ->test('navigation-chat')
        ->call('startConversation', $user2->id);

    // Get the created conversation
    $conversation = Conversation::query()->where('user1_id', min($user1->id, $user2->id))
        ->where('user2_id', max($user1->id, $user2->id))
        ->first();

    expect($conversation)->not->toBeNull();

    // Send first message through Chat component
    Livewire::actingAs($user1)
        ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
        ->set('messageText', 'Hello Bob!')
        ->call('sendMessage');

    // Verify conversation has the message
    $conversation->refresh();
    expect($conversation->messages()->count())->toBe(1);
    expect($conversation->last_message_id)->not->toBeNull();

    // User2 should see unread count
    expect($conversation->getUnreadCountForUser($user2))->toBe(1);

    // User2's navigation should show the conversation with unread badge
    Livewire::actingAs($user2)
        ->test('navigation-chat')
        ->assertSee('Alice')
        ->assertSee('1');
});
