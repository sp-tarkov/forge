<?php

declare(strict_types=1);

use App\Livewire\NavigationChat;
use App\Livewire\Page\Chat;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('can archive a conversation for a specific user', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);
    $conversation->messages()->create([
        'user_id' => $user1->id,
        'content' => 'Test message',
    ]);

    // Archive for user1
    $conversation->archiveFor($user1);

    // Check it's archived for user1
    expect($conversation->isArchivedBy($user1))->toBeTrue();
    expect($conversation->isArchivedBy($user2))->toBeFalse();
});

it('hides archived conversations from the chat list', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    // Create two conversations
    $conv1 = Conversation::findOrCreateBetween($user1, $user2, $user1);
    $conv2 = Conversation::findOrCreateBetween($user1, $user3, $user1);

    // Add messages to both
    $conv1->messages()->create(['user_id' => $user1->id, 'content' => 'Message 1']);
    $conv2->messages()->create(['user_id' => $user1->id, 'content' => 'Message 2']);

    // Test shows both conversations
    Livewire::actingAs($user1)
        ->test(Chat::class, ['conversationHash' => $conv1->hash_id])
        ->assertSee($user2->name)
        ->assertSee($user3->name);

    // Archive conv1 for user1
    $conv1->archiveFor($user1);

    // Test shows only conv2
    Livewire::actingAs($user1)
        ->test(Chat::class, ['conversationHash' => $conv2->hash_id])
        ->assertDontSee($user2->name)
        ->assertSee($user3->name);
});

it('hides archived conversations from navigation dropdown', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);
    $conversation->messages()->create([
        'user_id' => $user1->id,
        'content' => 'Test message',
    ]);

    // Before archiving - conversation is visible
    Livewire::actingAs($user1)
        ->test(NavigationChat::class)
        ->assertSee($user2->name);

    // Archive the conversation
    $conversation->archiveFor($user1);

    // After archiving - conversation is hidden
    Livewire::actingAs($user1)
        ->test(NavigationChat::class)
        ->assertDontSee($user2->name);
});

it('unarchives conversation when a new message is sent', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);
    $conversation->messages()->create([
        'user_id' => $user1->id,
        'content' => 'Initial message',
    ]);

    // Archive for both users
    $conversation->archiveFor($user1);
    $conversation->archiveFor($user2);

    expect($conversation->isArchivedBy($user1))->toBeTrue();
    expect($conversation->isArchivedBy($user2))->toBeTrue();

    // User2 sends a new message
    Livewire::actingAs($user2)
        ->test(Chat::class, ['conversationHash' => $conversation->hash_id])
        ->set('messageText', 'New message')
        ->call('sendMessage');

    // Conversation should be unarchived for both users
    $conversation->refresh();
    expect($conversation->isArchivedBy($user1))->toBeFalse();
    expect($conversation->isArchivedBy($user2))->toBeFalse();
});

it('shows conversation to other user when one user archives', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);
    $conversation->messages()->create([
        'user_id' => $user1->id,
        'content' => 'Test message',
    ]);

    // User1 archives the conversation
    $conversation->archiveFor($user1);

    // User1 doesn't see it in their list when accessing chat
    // (Will redirect to another conversation or empty state)
    $otherConv = Conversation::findOrCreateBetween($user1, User::factory()->create(), $user1);
    $otherConv->messages()->create(['user_id' => $user1->id, 'content' => 'Other message']);

    Livewire::actingAs($user1)
        ->test(Chat::class, ['conversationHash' => $otherConv->hash_id])
        ->assertDontSee($user2->name);

    // User2 still sees it
    Livewire::actingAs($user2)
        ->test(Chat::class, ['conversationHash' => $conversation->hash_id])
        ->assertSee($user1->name);
});

it('archives conversation through the UI', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);
    $conversation->messages()->create([
        'user_id' => $user1->id,
        'content' => 'Test message',
    ]);

    Livewire::actingAs($user1)
        ->test(Chat::class, ['conversationHash' => $conversation->hash_id])
        ->assertSee($user2->name)
        ->call('openArchiveModal')
        ->assertSet('showArchiveModal', true)
        ->call('archiveConversation')
        ->assertSet('showArchiveModal', false);

    // Verify conversation is archived
    expect($conversation->fresh()->isArchivedBy($user1))->toBeTrue();

    // Verify it's not shown in the list anymore
    // Create another conversation to avoid redirect issues
    $otherConv = Conversation::findOrCreateBetween($user1, User::factory()->create(), $user1);
    $otherConv->messages()->create(['user_id' => $user1->id, 'content' => 'Other message']);

    Livewire::actingAs($user1)
        ->test(Chat::class, ['conversationHash' => $otherConv->hash_id])
        ->assertDontSee($user2->name);
});
