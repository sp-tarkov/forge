<?php

declare(strict_types=1);

use App\Livewire\NavigationChat;
use App\Models\Conversation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user1 = User::factory()->create(['email_verified_at' => now()]);
    $this->user2 = User::factory()->create(['email_verified_at' => now()]);
    $this->bannedUser = User::factory()->create(['email_verified_at' => now()]);
    $this->bannedUser->ban();
    $this->unverifiedUser = User::factory()->create(['email_verified_at' => null]);
});

it('filters search results to show only verified non-banned users', function (): void {
    $this->actingAs($this->user1);

    Livewire::test(NavigationChat::class)
        ->set('searchUser', mb_substr((string) $this->user2->name, 0, 3))
        ->assertSee($this->user2->name)
        ->set('searchUser', mb_substr((string) $this->bannedUser->name, 0, 3))
        ->assertDontSee($this->bannedUser->name)
        ->set('searchUser', mb_substr((string) $this->unverifiedUser->name, 0, 3))
        ->assertDontSee($this->unverifiedUser->name);
});

it('allows creator to see conversation immediately without messages', function (): void {
    $this->actingAs($this->user1);

    // Create conversation with user1 as creator
    $conversation = Conversation::findOrCreateBetween($this->user1, $this->user2, $this->user1);

    // Creator should see the conversation
    expect($conversation->isVisibleTo($this->user1))->toBeTrue();

    // Check that it appears in the conversations list
    $conversations = Conversation::visibleTo($this->user1)->get();
    expect($conversations->contains('id', $conversation->id))->toBeTrue();
});

it('hides conversation from non-creator until first message is sent', function (): void {
    $this->actingAs($this->user1);

    // Create conversation with user1 as creator
    $conversation = Conversation::findOrCreateBetween($this->user1, $this->user2, $this->user1);

    // Non-creator should NOT see the conversation yet
    expect($conversation->isVisibleTo($this->user2))->toBeFalse();

    // Check that it doesn't appear in user2's conversations list
    $conversations = Conversation::visibleTo($this->user2)->get();
    expect($conversations->contains('id', $conversation->id))->toBeFalse();
});

it('shows conversation to both users after first message is sent', function (): void {
    $this->actingAs($this->user1);

    // Create conversation with user1 as creator
    $conversation = Conversation::findOrCreateBetween($this->user1, $this->user2, $this->user1);

    // Send first message
    $message = $conversation->messages()->create([
        'user_id' => $this->user1->id,
        'content' => 'Hello!',
    ]);

    // Update conversation's last message info
    $conversation->update([
        'last_message_id' => $message->id,
        'last_message_at' => now(),
    ]);

    // Both users should now see the conversation
    expect($conversation->isVisibleTo($this->user1))->toBeTrue();
    expect($conversation->isVisibleTo($this->user2))->toBeTrue();

    // Check that it appears in both users' conversations lists
    $user1Conversations = Conversation::visibleTo($this->user1)->get();
    $user2Conversations = Conversation::visibleTo($this->user2)->get();

    expect($user1Conversations->contains('id', $conversation->id))->toBeTrue();
    expect($user2Conversations->contains('id', $conversation->id))->toBeTrue();
});

it('redirects creator to conversation page when starting new conversation', function (): void {
    $this->actingAs($this->user1);

    Livewire::test(NavigationChat::class)
        ->set('searchUser', $this->user2->name)
        ->call('startConversation', $this->user2->id)
        ->assertRedirect(route('chat', ['conversationHash' => Conversation::query()->first()->hash_id]));

    // Verify conversation was created with correct creator
    $conversation = Conversation::query()->first();
    expect($conversation->created_by)->toBe($this->user1->id);
});

it('enforces conversation visibility in the policy', function (): void {
    // Create conversation with user1 as creator
    $conversation = Conversation::findOrCreateBetween($this->user1, $this->user2, $this->user1);

    // Creator can view
    expect($this->user1->can('view', $conversation))->toBeTrue();

    // Non-creator cannot view without messages
    expect($this->user2->can('view', $conversation))->toBeFalse();

    // Send message
    $message = $conversation->messages()->create([
        'user_id' => $this->user1->id,
        'content' => 'Hello!',
    ]);
    $conversation->update([
        'last_message_id' => $message->id,
        'last_message_at' => now(),
    ]);

    // Now non-creator can view
    expect($this->user2->can('view', $conversation))->toBeTrue();
});

it('allows both participants to send messages regardless of visibility', function (): void {
    // Create conversation with user1 as creator
    $conversation = Conversation::findOrCreateBetween($this->user1, $this->user2, $this->user1);

    // Both users can send messages (even if user2 can't see the conversation yet)
    expect($this->user1->can('sendMessage', $conversation))->toBeTrue();
    expect($this->user2->can('sendMessage', $conversation))->toBeTrue();
});

it('only shows visible conversations in the chat page', function (): void {
    // Create two conversations
    $visibleConversation = Conversation::findOrCreateBetween($this->user1, $this->user2, $this->user1);

    // Add message to make it visible
    $message = $visibleConversation->messages()->create([
        'user_id' => $this->user1->id,
        'content' => 'Hello!',
    ]);
    $visibleConversation->update([
        'last_message_id' => $message->id,
        'last_message_at' => now(),
    ]);

    // Create another conversation where user2 is not the creator and has no messages
    $user3 = User::factory()->create(['email_verified_at' => now()]);
    $hiddenConversation = Conversation::findOrCreateBetween($this->user2, $user3, $user3);

    // User2 should only see the visible conversation
    $this->actingAs($this->user2);

    // Verify using database query
    $userConversations = Conversation::visibleTo($this->user2)->get();
    expect($userConversations->contains('id', $visibleConversation->id))->toBeTrue();
    expect($userConversations->contains('id', $hiddenConversation->id))->toBeFalse();
});
