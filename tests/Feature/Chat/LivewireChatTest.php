<?php

declare(strict_types=1);

use App\Livewire\Page\Chat;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('can send a message in a conversation', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

    Livewire::actingAs($user1)
        ->test(Chat::class, ['conversationHash' => $conversation->hash_id])
        ->assertOk()
        ->set('messageText', 'Hello, this is a test message!')
        ->call('sendMessage')
        ->assertSet('messageText', '')
        ->assertSee('Hello, this is a test message!');

    // Verify the message was saved
    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'user_id' => $user1->id,
        'content' => 'Hello, this is a test message!',
    ]);
});

it('refreshes messages after sending', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

    $component = Livewire::actingAs($user1)
        ->test(Chat::class, ['conversationHash' => $conversation->hash_id])
        ->assertOk();

    // Send first message
    $component->set('messageText', 'First message')
        ->call('sendMessage')
        ->assertSee('First message');

    // Send second message
    $component->set('messageText', 'Second message')
        ->call('sendMessage')
        ->assertSee('First message')
        ->assertSee('Second message');

    // Verify both messages are in the database
    $this->assertEquals(2, $conversation->messages()->count());
});

it('maintains selected conversation when switching and then loading more messages', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    // Create two conversations with many messages
    $conv1 = Conversation::findOrCreateBetween($user1, $user2, $user1);
    $conv2 = Conversation::findOrCreateBetween($user1, $user3, $user1);

    // Add many messages to both conversations
    for ($i = 1; $i <= 30; $i++) {
        $conv1->messages()->create([
            'user_id' => $i % 2 === 0 ? $user1->id : $user2->id,
            'content' => sprintf('Message %d in conversation 1', $i),
        ]);
        $conv2->messages()->create([
            'user_id' => $i % 2 === 0 ? $user1->id : $user3->id,
            'content' => sprintf('Message %d in conversation 2', $i),
        ]);
    }

    // Start with conversation 1
    $component = Livewire::actingAs($user1)
        ->test(Chat::class, ['conversationHash' => $conv1->hash_id])
        ->assertOk()
        ->assertSet('selectedConversation.id', $conv1->id)
        ->assertSet('conversationHash', $conv1->hash_id)
        ->assertSee('Message 30 in conversation 1');

    // Switch to conversation 2
    $component->call('switchConversation', $conv2->hash_id)
        ->assertSet('selectedConversation.id', $conv2->id)
        ->assertSet('conversationHash', $conv2->hash_id);

    // Now load more messages - should stay on conversation 2
    $component->call('loadMoreMessages')
        ->assertSet('pagesLoaded', 2)
        ->assertSet('selectedConversation.id', $conv2->id) // Should still be conversation 2
        ->assertSet('conversationHash', $conv2->hash_id); // Hash should still be conversation 2
});

it('maintains selected conversation when loading more messages', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    // Create two conversations
    $conv1 = Conversation::findOrCreateBetween($user1, $user2, $user1);
    $conv2 = Conversation::findOrCreateBetween($user1, $user3, $user1);

    // Add many messages to conversation 1 to enable pagination
    for ($i = 1; $i <= 30; $i++) {
        $conv1->messages()->create([
            'user_id' => $i % 2 === 0 ? $user1->id : $user2->id,
            'content' => sprintf('Message %d in conversation 1', $i),
        ]);
    }

    // Add a message to conversation 2
    $conv2->messages()->create([
        'user_id' => $user3->id,
        'content' => 'Message in conversation 2',
    ]);

    // Test loading more messages doesn't switch conversations
    $component = Livewire::actingAs($user1)
        ->test(Chat::class, ['conversationHash' => $conv1->hash_id])
        ->assertOk()
        ->assertSet('selectedConversation.id', $conv1->id)
        ->assertSet('pagesLoaded', 1)
        ->assertSet('hasMoreMessages', true);

    // Load more messages
    $component->call('loadMoreMessages')
        ->assertSet('pagesLoaded', 2)
        ->assertSet('selectedConversation.id', $conv1->id); // Should still be conversation 1

    // Verify we can see more messages now
    $component->assertSee('Message 11 in conversation 1');
});

it('shows conversations list properly', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    // Create conversations
    $conv1 = Conversation::findOrCreateBetween($user1, $user2, $user1);
    $conv2 = Conversation::findOrCreateBetween($user1, $user3, $user1);

    // Add messages to make conversations visible
    $conv1->messages()->create([
        'user_id' => $user1->id,
        'content' => 'Message to user 2',
    ]);

    $conv2->messages()->create([
        'user_id' => $user3->id,
        'content' => 'Message to user 3',
    ]);

    // Test with a specific conversation selected
    Livewire::actingAs($user1)
        ->test(Chat::class, ['conversationHash' => $conv1->hash_id])
        ->assertOk()
        ->assertSee($user2->name)
        ->assertSee($user3->name)
        ->assertSee('Message to user 2')
        ->assertSee('Message to user 3');
});
