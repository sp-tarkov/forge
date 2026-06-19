<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\ConversationSubscription;
use App\Models\Message;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

/**
 * Creates a verified, non-banned user that is eligible to appear in conversation search results.
 */
function makeSearchableConversationUser(string $name): User
{
    return User::factory()->create([
        'name' => $name,
        'email_verified_at' => now(),
    ]);
}

describe('participants and relationships', function (): void {
    it('can create a conversation between two users', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        expect($conversation)->toBeInstanceOf(Conversation::class)
            ->and($conversation->user1_id)->toBe($user1->id)
            ->and($conversation->user2_id)->toBe($user2->id);
    });

    it('ensures users are ordered consistently in conversations', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create conversation with users in different order
        $conversation1 = Conversation::findOrCreateBetween($user1, $user2);
        $conversation2 = Conversation::findOrCreateBetween($user2, $user1);

        expect($conversation1->id)->toBe($conversation2->id)
            ->and($conversation1->user1_id)->toBe(min($user1->id, $user2->id))
            ->and($conversation1->user2_id)->toBe(max($user1->id, $user2->id));
    });

    it('prevents duplicate conversations between same users', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation1 = Conversation::findOrCreateBetween($user1, $user2);
        $conversation2 = Conversation::findOrCreateBetween($user1, $user2);

        expect($conversation1->id)->toBe($conversation2->id)
            ->and(Conversation::query()->count())->toBe(1);
    });

    it('has relationships with users', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        expect($conversation->user1)->toBeInstanceOf(User::class)
            ->and($conversation->user1->id)->toBe($user1->id)
            ->and($conversation->user2)->toBeInstanceOf(User::class)
            ->and($conversation->user2->id)->toBe($user2->id);
    });

    it('can get the other user in a conversation', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        expect($conversation->getOtherUser($user1)?->id)->toBe($user2->id)
            ->and($conversation->getOtherUser($user2)?->id)->toBe($user1->id);
    });

    it('returns null for users not in the conversation', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        expect($conversation->getOtherUser($user3))->toBeNull();
    });

    it('can check if a user is part of a conversation', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        expect($conversation->hasUser($user1))->toBeTrue()
            ->and($conversation->hasUser($user2))->toBeTrue()
            ->and($conversation->hasUser($user3))->toBeFalse();
    });

    it('can scope conversations for a specific user', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // User1 has conversations with User2 and User3
        $conversation1 = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        $conversation2 = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user3->id,
        ]);

        // User2 has conversation with User3 (without User1)
        $conversation3 = Conversation::query()->create([
            'user1_id' => $user2->id,
            'user2_id' => $user3->id,
        ]);

        $user1Conversations = Conversation::forUser($user1)->get();

        expect($user1Conversations)->toHaveCount(2)
            ->and($user1Conversations->pluck('id')->toArray())
            ->toContain($conversation1->id, $conversation2->id)
            ->not->toContain($conversation3->id);
    });
});

describe('messages and last message tracking', function (): void {
    it('updates last message fields when a message is created', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        expect($conversation->last_message_id)->toBeNull()
            ->and($conversation->last_message_at)->toBeNull();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'Hello!',
        ]);

        $conversation->refresh();

        expect($conversation->last_message_id)->toBe($message->id)
            ->and($conversation->last_message_at?->timestamp)->toBe($message->created_at->timestamp);
    });

    it('orders messages by creation date ascending', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        $message3 = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'Third',
            'created_at' => now()->addMinutes(3),
        ]);

        $message1 = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'First',
            'created_at' => now()->addMinutes(1),
        ]);

        $message2 = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user2->id,
            'content' => 'Second',
            'created_at' => now()->addMinutes(2),
        ]);

        $messages = $conversation->messages()->get();

        expect($messages->pluck('id')->toArray())->toBe([
            $message1->id,
            $message2->id,
            $message3->id,
        ]);
    });
});

describe('unread tracking', function (): void {
    it('can get unread message count for a user', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        // User1 sends messages
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'Message 1',
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'Message 2',
        ]);

        // User2 sends a message (should not count as unread for User2)
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user2->id,
            'content' => 'Reply',
        ]);

        expect($conversation->getUnreadCountForUser($user2))->toBe(2)
            ->and($conversation->getUnreadCountForUser($user1))->toBe(1);
    });

    it('can mark all messages as read for a user', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        // User1 sends messages
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'Message 1',
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'Message 2',
        ]);

        expect($conversation->getUnreadCountForUser($user2))->toBe(2);

        $conversation->markReadBy($user2);

        expect($conversation->getUnreadCountForUser($user2))->toBe(0);

        // Check that both messages have been marked as read by user2
        foreach ($conversation->messages as $message) {
            expect($message->isReadBy($user2))->toBeTrue();
        }
    });

    it('can scope conversations with unread messages', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Conversation with unread messages for User2
        $conversation1 = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        Message::query()->create([
            'conversation_id' => $conversation1->id,
            'user_id' => $user1->id,
            'content' => 'Unread message',
        ]);

        // Conversation with all messages read
        $conversation2 = Conversation::query()->create([
            'user1_id' => $user2->id,
            'user2_id' => $user3->id,
        ]);

        $readMessage = Message::query()->create([
            'conversation_id' => $conversation2->id,
            'user_id' => $user3->id,
            'content' => 'Read message',
        ]);

        // Mark as read by user2
        $readMessage->markAsReadBy($user2);

        $unreadConversations = Conversation::withUnreadMessages($user2)->get();

        expect($unreadConversations)->toHaveCount(1)
            ->and($unreadConversations->first()->id)->toBe($conversation1->id);
    });
});

describe('visibility scope', function (): void {
    it('allows creator to see conversation immediately without messages', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create conversation with user1 as creator
        $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

        // Creator should see the conversation
        expect($conversation->isVisibleTo($user1))->toBeTrue();

        // Check that it appears in the conversations list
        $conversations = Conversation::visibleTo($user1)->get();
        expect($conversations->contains('id', $conversation->id))->toBeTrue();
    });

    it('hides conversation from non-creator until first message is sent', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create conversation with user1 as creator
        $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

        // Non-creator should NOT see the conversation yet
        expect($conversation->isVisibleTo($user2))->toBeFalse();

        // Check that it doesn't appear in user2's conversations list
        $conversations = Conversation::visibleTo($user2)->get();
        expect($conversations->contains('id', $conversation->id))->toBeFalse();
    });

    it('shows conversation to both users after first message is sent', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create conversation with user1 as creator
        $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

        // Send first message
        $message = $conversation->messages()->create([
            'user_id' => $user1->id,
            'content' => 'Hello!',
        ]);

        // Update conversation's last message info
        $conversation->update([
            'last_message_id' => $message->id,
            'last_message_at' => now(),
        ]);

        // Both users should now see the conversation
        expect($conversation->isVisibleTo($user1))->toBeTrue();
        expect($conversation->isVisibleTo($user2))->toBeTrue();

        // Check that it appears in both users' conversations lists
        $user1Conversations = Conversation::visibleTo($user1)->get();
        $user2Conversations = Conversation::visibleTo($user2)->get();

        expect($user1Conversations->contains('id', $conversation->id))->toBeTrue();
        expect($user2Conversations->contains('id', $conversation->id))->toBeTrue();
    });

    it('shows conversation to the other user after the first message is sent via the relation', function (): void {
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

        // User2 should see the conversation in navigation with an unread badge
        Livewire::actingAs($user2)
            ->test('navigation-chat')
            ->assertSee('Alice')
            ->assertSee('1');
    });

    it('only shows visible conversations in the chat page', function (): void {
        $user1 = User::factory()->create(['email_verified_at' => now()]);
        $user2 = User::factory()->create(['email_verified_at' => now()]);

        // Create two conversations
        $visibleConversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

        // Add message to make it visible
        $message = $visibleConversation->messages()->create([
            'user_id' => $user1->id,
            'content' => 'Hello!',
        ]);
        $visibleConversation->update([
            'last_message_id' => $message->id,
            'last_message_at' => now(),
        ]);

        // Create another conversation where user2 is not the creator and has no messages
        $user3 = User::factory()->create(['email_verified_at' => now()]);
        $hiddenConversation = Conversation::findOrCreateBetween($user2, $user3, $user3);

        // User2 should only see the visible conversation
        $this->actingAs($user2);

        // Verify using database query
        $userConversations = Conversation::visibleTo($user2)->get();
        expect($userConversations->contains('id', $visibleConversation->id))->toBeTrue();
        expect($userConversations->contains('id', $hiddenConversation->id))->toBeFalse();
    });
});

describe('archiving', function (): void {
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
            ->test('pages::chat', ['conversationHash' => $conv1->hash_id])
            ->assertSee($user2->name)
            ->assertSee($user3->name);

        // Archive conv1 for user1
        $conv1->archiveFor($user1);

        // Test shows only conv2
        Livewire::actingAs($user1)
            ->test('pages::chat', ['conversationHash' => $conv2->hash_id])
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
            ->test('navigation-chat')
            ->assertSee($user2->name);

        // Archive the conversation
        $conversation->archiveFor($user1);

        // After archiving - conversation is hidden
        Livewire::actingAs($user1)
            ->test('navigation-chat')
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
            ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
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

        // User1 doesn't see it in their list when accessing chat (will redirect to another conversation or empty state)
        $otherConv = Conversation::findOrCreateBetween($user1, User::factory()->create(), $user1);
        $otherConv->messages()->create(['user_id' => $user1->id, 'content' => 'Other message']);

        Livewire::actingAs($user1)
            ->test('pages::chat', ['conversationHash' => $otherConv->hash_id])
            ->assertDontSee($user2->name);

        // User2 still sees it
        Livewire::actingAs($user2)
            ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
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
            ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
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
            ->test('pages::chat', ['conversationHash' => $otherConv->hash_id])
            ->assertDontSee($user2->name);
    });
});

describe('notification preferences', function (): void {
    it('can toggle notification preferences for a conversation', function (): void {
        $user = User::factory()->create([
            'email_chat_notifications_enabled' => true,
        ]);
        $otherUser = User::factory()->create();

        $conversation = Conversation::findOrCreateBetween($user, $otherUser, $user);

        Livewire::actingAs($user)
            ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->assertSee('Disable Notifications')
            ->call('toggleNotifications')
            ->assertSee('Enable Notifications');

        // Check that subscription was saved
        $subscription = ConversationSubscription::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        expect($subscription)->not->toBeNull();
        expect($subscription->notifications_enabled)->toBeFalse();

        // Toggle back on
        Livewire::actingAs($user)
            ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->call('toggleNotifications');

        $subscription->refresh();
        expect($subscription->notifications_enabled)->toBeTrue();
    });

    it('shows correct notification status in dropdown', function (): void {
        $user = User::factory()->create([
            'email_chat_notifications_enabled' => true,
        ]);
        $otherUser = User::factory()->create();

        $conversation = Conversation::findOrCreateBetween($user, $otherUser, $user);

        // Should show "Disable Notifications" initially (using global preference)
        Livewire::actingAs($user)
            ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->assertSee('Disable Notifications')
            ->assertDontSee('Enable Notifications');

        // Disable notifications for this conversation
        $conversation->subscriptions()->create([
            'user_id' => $user->id,
            'notifications_enabled' => false,
        ]);

        // Should now show "Enable Notifications"
        Livewire::actingAs($user)
            ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->assertSee('Enable Notifications')
            ->assertDontSee('Disable Notifications');
    });

    it('respects global preference when no conversation-specific preference exists', function (): void {
        $userWithGlobalEnabled = User::factory()->create([
            'email_chat_notifications_enabled' => true,
        ]);
        $userWithGlobalDisabled = User::factory()->create([
            'email_chat_notifications_enabled' => false,
        ]);
        $otherUser = User::factory()->create();

        $conversation1 = Conversation::findOrCreateBetween($userWithGlobalEnabled, $otherUser, $userWithGlobalEnabled);
        $conversation2 = Conversation::findOrCreateBetween($userWithGlobalDisabled, $otherUser, $userWithGlobalDisabled);

        // User with global enabled should see "Disable" option
        Livewire::actingAs($userWithGlobalEnabled)
            ->test('pages::chat', ['conversationHash' => $conversation1->hash_id])
            ->assertSee('Disable Notifications');

        // User with global disabled should see "Enable" option
        Livewire::actingAs($userWithGlobalDisabled)
            ->test('pages::chat', ['conversationHash' => $conversation2->hash_id])
            ->assertSee('Enable Notifications');
    });
});

describe('conversation search ordering', function (): void {
    beforeEach(function (): void {
        $this->currentUser = User::factory()->create(['name' => 'Current User']);
        $this->actingAs($this->currentUser);
    });

    it('prioritizes exact matches over partial matches', function (): void {
        // Create users with similar names - the exact match should come first
        $exactMatch = User::factory()->create(['name' => 'testing']);
        User::factory()->create(['name' => 'testinguser']);
        User::factory()->create(['name' => 'testingperson']);
        User::factory()->create(['name' => 'atestingb']);

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        // Exact match should be first
        expect($results->first()->id)->toBe($exactMatch->id);
    });

    it('prioritizes starts-with matches over contains matches', function (): void {
        // Create users where some start with the term and others contain it
        $startsWithMatch = User::factory()->create(['name' => 'testinguser']);
        User::factory()->create(['name' => 'xtestingy']);

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        // Starts-with should come before contains
        expect($results->first()->id)->toBe($startsWithMatch->id);
    });

    it('orders results by exact match then starts-with then contains', function (): void {
        // Create users in a specific order to test the full ordering
        $containsMatch = User::factory()->create(['name' => 'mytestingname']);
        $startsWithMatch = User::factory()->create(['name' => 'testingexample']);
        $exactMatch = User::factory()->create(['name' => 'testing']);

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        // Results should be ordered: exact -> starts-with -> contains
        expect($results->count())->toBe(3)
            ->and($results->get(0)->id)->toBe($exactMatch->id)
            ->and($results->get(1)->id)->toBe($startsWithMatch->id)
            ->and($results->get(2)->id)->toBe($containsMatch->id);
    });

    it('handles case-insensitive exact matching', function (): void {
        $exactMatch = User::factory()->create(['name' => 'Testing']);
        User::factory()->create(['name' => 'testinguser']);

        // Search with lowercase
        $results = User::conversationSearch($this->currentUser, 'testing')->get();
        expect($results->first()->id)->toBe($exactMatch->id);

        // Search with uppercase
        $results = User::conversationSearch($this->currentUser, 'TESTING')->get();
        expect($results->first()->id)->toBe($exactMatch->id);
    });

    it('returns exact match first when there are many similar usernames', function (): void {
        // Create many users with similar names (simulating the original issue)
        $exactMatch = User::factory()->create(['name' => 'testing']);

        for ($i = 1; $i <= 15; $i++) {
            User::factory()->create(['name' => 'testinguser'.$i]);
        }

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        // Even with limit of 10, exact match should be first
        expect($results->first()->id)->toBe($exactMatch->id)
            ->and($results->count())->toBeLessThanOrEqual(10);
    });

    it('orders alphabetically within the same match tier', function (): void {
        // Create users that all start with the search term
        User::factory()->create(['name' => 'testingC']);
        User::factory()->create(['name' => 'testingA']);
        User::factory()->create(['name' => 'testingB']);

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        // All start-with matches, should be ordered alphabetically
        expect($results->get(0)->name)->toBe('testingA')
            ->and($results->get(1)->name)->toBe('testingB')
            ->and($results->get(2)->name)->toBe('testingC');
    });

    it('does not include the current user in search results', function (): void {
        // Current user's name starts with the search term
        $this->currentUser->update(['name' => 'testingme']);

        User::factory()->create(['name' => 'testingother']);

        $results = User::conversationSearch($this->currentUser, 'testing')->get();

        expect($results->pluck('id')->toArray())->not->toContain($this->currentUser->id)
            ->and($results)->toHaveCount(1);
    });

    it('filters search results to show only verified non-banned users', function (): void {
        $user1 = makeSearchableConversationUser('Verified Match One');
        $verifiedTarget = makeSearchableConversationUser('Verified Match Two');

        $bannedUser = makeSearchableConversationUser('Banned Match');
        $bannedUser->ban();

        $unverifiedUser = User::factory()->create([
            'name' => 'Unverified Match',
            'email_verified_at' => null,
        ]);

        $this->actingAs($user1);

        Livewire::test('navigation-chat')
            ->set('searchUser', mb_substr((string) $verifiedTarget->name, 0, 8))
            ->assertSee($verifiedTarget->name)
            ->set('searchUser', mb_substr((string) $bannedUser->name, 0, 6))
            ->assertDontSee($bannedUser->name)
            ->set('searchUser', mb_substr((string) $unverifiedUser->name, 0, 10))
            ->assertDontSee($unverifiedUser->name);
    });
});

describe('new conversation modal search', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('displays the search input with correct binding in new conversation modal', function (): void {
        // Create other users to search for
        User::factory()->create(['name' => 'John Doe']);

        Livewire::test('pages::chat')
            ->assertSet('searchUser', '') // Initially empty
            ->set('showNewConversation', true) // Open the modal
            ->assertSee('Start typing to search for users')
            ->set('searchUser', 'John')
            ->assertSet('searchUser', 'John')
            ->assertDontSee('No users found matching "searchUser"') // Should not show literal "searchUser"
            ->assertSee('John Doe');
    });

    it('shows no results message with actual search term when no users found', function (): void {
        Livewire::test('pages::chat')
            ->assertSet('searchUser', '')
            ->set('showNewConversation', true) // Open the modal
            ->set('searchUser', 'NonexistentUser')
            ->assertSet('searchUser', 'NonexistentUser')
            ->assertSee('No users found matching')
            ->assertSee('NonexistentUser'); // Check separately due to potential HTML escaping
    });

    it('shows empty state message when search input is empty', function (): void {
        Livewire::test('pages::chat')
            ->assertSet('searchUser', '')
            ->set('showNewConversation', true) // Open the modal
            ->assertSee('Start typing to search for users')
            ->assertDontSee('No users found matching');
    });

    it('clears search results when search input is cleared', function (): void {
        User::factory()->create(['name' => 'Jane Smith']);

        Livewire::test('pages::chat')
            ->set('showNewConversation', true) // Open the modal
            ->set('searchUser', 'Jane')
            ->assertSee('Jane Smith')
            ->set('searchUser', '')
            ->assertDontSee('Jane Smith')
            ->assertSee('Start typing to search for users');
    });

    it('displays mod count for users with published mods', function (): void {
        $userWithMods = User::factory()->create(['name' => 'Mod Creator']);

        // Create published mods for the user
        Mod::factory()
            ->count(3)
            ->create([
                'owner_id' => $userWithMods->id,
                'published_at' => now()->subDays(10),
            ]);

        // Create unpublished mod (shouldn't be counted)
        Mod::factory()->create([
            'owner_id' => $userWithMods->id,
            'published_at' => null,
        ]);

        Livewire::test('pages::chat')
            ->set('showNewConversation', true) // Open the modal
            ->set('searchUser', 'Mod Creator')
            ->assertSee('Mod Creator')
            ->assertSee('3 mods');
    });

    it('excludes users who have blocked the current user from search', function (): void {
        $blockerUser = User::factory()->create(['name' => 'Blocker User']);
        User::factory()->create(['name' => 'Normal User']);

        // blockerUser blocks the current user
        $blockerUser->block($this->user);

        Livewire::test('pages::chat')
            ->set('showNewConversation', true)
            ->set('searchUser', 'User')
            ->assertDontSee('Blocker User')
            ->assertSee('Normal User');
    });

    it('excludes mutually blocked users from search', function (): void {
        $mutuallyBlockedUser = User::factory()->create(['name' => 'Mutually Blocked']);
        User::factory()->create(['name' => 'Normal Person']);

        // Both users block each other
        $this->user->block($mutuallyBlockedUser);
        $mutuallyBlockedUser->block($this->user);

        Livewire::test('pages::chat')
            ->set('showNewConversation', true)
            ->set('searchUser', 'lock') // Should match "Blocked" in the name
            ->assertDontSee('Mutually Blocked')
            ->set('searchUser', 'Normal')
            ->assertSee('Normal Person');
    });
});

describe('starting conversations', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($this->user);
    });

    it('prevents starting conversations with users who blocked current user', function (): void {
        $blockerUser = User::factory()->create(['name' => 'Blocker User']);

        // blockerUser blocks the current user
        $blockerUser->block($this->user);

        Livewire::test('navigation-chat')
            ->call('startConversation', $blockerUser->id)
            ->assertSet('showNewConversation', false)
            ->assertNoRedirect();

        // Verify no conversation was created
        expect(Conversation::query()->count())->toBe(0);
    });

    it('prevents starting conversations with users current user has blocked', function (): void {
        $blockedUser = User::factory()->create(['name' => 'Blocked User']);

        // Current user blocks blockedUser
        $this->user->block($blockedUser);

        Livewire::test('navigation-chat')
            ->call('startConversation', $blockedUser->id)
            ->assertSet('showNewConversation', false)
            ->assertNoRedirect();

        // Verify no conversation was created
        expect(Conversation::query()->count())->toBe(0);
    });

    it('allows starting conversations with non-blocked users', function (): void {
        User::factory()->create(['name' => 'Normal User']);
        $target = User::factory()->create(['name' => 'Reachable User']);

        Livewire::test('navigation-chat')
            ->call('startConversation', $target->id)
            ->assertRedirect()
            ->assertSet('showNewConversation', false);

        // No error flash message should be set
        expect(session('flash_notification.level'))->not->toBe('error');
    });

    it('redirects creator to conversation page when starting new conversation', function (): void {
        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        Livewire::test('navigation-chat')
            ->set('searchUser', $otherUser->name)
            ->call('startConversation', $otherUser->id)
            ->assertRedirect(route('chat', ['conversationHash' => Conversation::query()->first()->hash_id]));

        // Verify conversation was created with correct creator
        $conversation = Conversation::query()->first();
        expect($conversation->created_by)->toBe($this->user->id);
    });
});
