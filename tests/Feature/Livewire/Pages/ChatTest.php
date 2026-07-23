<?php

declare(strict_types=1);

use App\Events\UserBlocked;
use App\Events\UserUnblocked;
use App\Models\Conversation;
use App\Models\ConversationArchive;
use App\Models\Message;
use App\Models\User;
use App\Services\UserBlockingService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

describe('page render', function (): void {
    it('requires authentication to access chat page', function (): void {
        $response = $this->get('/chat');

        $response->assertRedirect('/login');
    });

    it('allows authenticated users to access chat page', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/chat');

        $response->assertOk();
    });

    it('renders the chat component correctly', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::chat')
            ->assertOk();
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
            ->test('pages::chat', ['conversationHash' => $conv1->hash_id])
            ->assertOk()
            ->assertSee($user2->name)
            ->assertSee($user3->name)
            ->assertSee('Message to user 2')
            ->assertSee('Message to user 3');
    });
});

describe('messaging', function (): void {
    it('can send a message in a conversation', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

        Livewire::actingAs($user1)
            ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
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
            ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
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

    it('correctly tracks unread count after first message via Chat component', function (): void {
        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);

        // User1 starts conversation through the navigation component
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
            ->test('pages::chat', ['conversationHash' => $conv1->hash_id])
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
            ->test('pages::chat', ['conversationHash' => $conv1->hash_id])
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
});

describe('unread badges in navigation', function (): void {
    it('shows unread badge for other user when first message is sent', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User1 starts a conversation with User2
        $conversation = Conversation::findOrCreateBetween($user1, $user2, $user1);

        // User1 sends a message
        Livewire::actingAs($user1)
            ->test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->set('messageText', 'Hello there!')
            ->call('sendMessage');

        // Check that User2 sees unread badge in navigation
        Livewire::actingAs($user2)
            ->test('navigation-chat')
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
            ->test('navigation-chat')
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
            ->test('navigation-chat')
            ->assertSee('2'); // Total unread conversations
    });
});

describe('blocking UI', function (): void {
    beforeEach(function (): void {
        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();
        $this->actingAs($this->userA);
    });

    it('shows block option in conversation dropdown menu', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->assertSee('Block User');
    });

    it('shows unblock option when user is already blocked', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->assertSee('Unblock User');
    });

    it('shows block modal when block option is clicked', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->call('openBlockModal')
            ->assertSet('showBlockModal', true)
            ->assertSee('What happens when you block someone');
    });

    it('blocks user when confirm is clicked', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->set('blockReason', 'Test reason')
            ->call('confirmBlock');

        expect($this->userA->hasBlocked($this->userB))->toBeTrue();
    });

    it('unblocks user when already blocked', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->call('confirmBlock');

        expect($this->userA->fresh()->hasBlocked($this->userB))->toBeFalse();
    });
});

describe('blocking and archiving interaction', function (): void {
    beforeEach(function (): void {
        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();
        $this->actingAs($this->userA);
    });

    it('does not automatically archive conversations when blocking', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        $service = new UserBlockingService;
        $service->blockUser($this->userA, $this->userB);

        expect(ConversationArchive::query()->where('conversation_id', $conversation->id)->count())->toBe(0);
    });

    it('allows manual archiving of blocked conversations', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->call('archiveConversation');

        expect($conversation->isArchivedBy($this->userA))->toBeTrue();
    });

    it('prevents starting conversations with blocked users through unarchiving', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $conversation->archiveFor($this->userA);

        $this->userB->block($this->userA);

        Livewire::test('pages::chat')
            ->call('startConversation', $this->userB->id)
            ->assertNoRedirect();

        // Conversation should still be archived
        expect($conversation->isArchivedBy($this->userA))->toBeTrue();
    });

    it('prevents starting a new conversation with a user who has blocked you', function (): void {
        $this->userB->block($this->userA);

        Livewire::test('pages::chat')
            ->call('startConversation', $this->userB->id)
            ->assertSet('selectedConversation', null);

        expect(Conversation::query()->count())->toBe(0);
    });

    it('prevents starting a new conversation with a user you have blocked', function (): void {
        $this->userA->block($this->userB);

        Livewire::test('pages::chat')
            ->call('startConversation', $this->userB->id)
            ->assertSet('selectedConversation', null);

        expect(Conversation::query()->count())->toBe(0);
    });

    it('allows blocker to search for and unarchive conversations with blocked users', function (): void {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create(['name' => 'UniqueBlockedUser_'.uniqid()]);

        // Create conversation and add a message
        $conversation = Conversation::factory()->create([
            'user1_id' => $blocker->id,
            'user2_id' => $blocked->id,
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $blocker->id,
            'content' => 'test message',
        ]);

        // Blocker blocks the user
        $blocker->block($blocked);

        // Blocker archives the conversation
        $conversation->archiveFor($blocker);

        // Verify conversation is archived
        expect($conversation->isArchivedBy($blocker))->toBeTrue();

        // Blocker should be able to find the blocked user in search
        $searchResults = User::conversationSearch($blocker, 'UniqueBlockedUser')->get();
        expect($searchResults)->toHaveCount(1);
        expect($searchResults->first()->id)->toBe($blocked->id);

        // Blocker CAN unarchive their own archived conversation (they're the blocker)
        $this->actingAs($blocker);
        expect($blocker->can('unarchive', $conversation))->toBeTrue();

        // Test via Livewire component
        Livewire::test('pages::chat')
            ->set('searchUser', 'UniqueBlockedUser')
            ->call('startConversation', $blocked->id)
            ->assertSet('selectedConversation.id', $conversation->id);

        // Conversation should be unarchived
        $conversation->refresh();
        expect($conversation->isArchivedBy($blocker))->toBeFalse();
    });

    it('allows unarchiving after unblocking', function (): void {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create(['name' => 'Test User']);

        // Create conversation with message
        $conversation = Conversation::factory()->create([
            'user1_id' => $blocker->id,
            'user2_id' => $blocked->id,
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $blocker->id,
            'content' => 'test',
        ]);

        // Block and archive
        $blocker->block($blocked);
        $conversation->archiveFor($blocker);

        // Unblock
        $blocker->unblock($blocked);

        // Should be able to search and unarchive
        $this->actingAs($blocker);
        expect($blocker->can('unarchive', $conversation))->toBeTrue();

        $searchResults = User::conversationSearch($blocker, 'Test')->get();
        expect($searchResults)->toHaveCount(1);

        // Unarchive via startConversation
        Livewire::test('pages::chat')
            ->call('startConversation', $blocked->id)
            ->assertSet('selectedConversation.id', $conversation->id);

        expect($conversation->fresh()->isArchivedBy($blocker))->toBeFalse();
    });
});

describe('blocked message sending', function (): void {
    beforeEach(function (): void {
        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();
        $this->actingAs($this->userA);
    });

    it('shows blocked message instead of input when either user blocks', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->assertSee("You can't send messages in this conversation")
            ->assertDontSee('Type a message...');
    });

    it('shows blocked message when blocked by other user', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userB->block($this->userA);

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->assertSee("You can't send messages in this conversation");
    });

    it('prevents sending messages when blocked', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->set('messageText', 'Test message')
            ->call('sendMessage');

        expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(0);
    });

    it('allows sending messages after unblocking', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);
        $this->userA->unblock($this->userB);

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->set('messageText', 'Test message')
            ->call('sendMessage');

        expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(1);
    });
});

describe('blocking search behavior', function (): void {
    beforeEach(function (): void {
        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();
        $this->actingAs($this->userA);
    });

    it('excludes users who have blocked the current user from search', function (): void {
        $blockerUser = User::factory()->create(['name' => 'Blocker User']);
        User::factory()->create(['name' => 'Normal User']);

        $blockerUser->block($this->userA);

        Livewire::test('pages::chat')
            ->set('showNewConversation', true)
            ->set('searchUser', 'User')
            ->assertDontSee('Blocker User')
            ->assertSee('Normal User');
    });

    it('allows users to search for users they have blocked', function (): void {
        $blockedUser = User::factory()->create(['name' => 'Blocked User']);
        User::factory()->create(['name' => 'Normal User']);

        $this->userA->block($blockedUser);

        // Blocker CAN see blocked users in search (to unarchive conversations)
        Livewire::test('pages::chat')
            ->set('showNewConversation', true)
            ->set('searchUser', 'User')
            ->assertSee('Blocked User')
            ->assertSee('Normal User');
    });

    it('allows blocker to search for blocked users', function (): void {
        $blockedUser = User::factory()->create(['name' => 'Blocked Person']);

        // UserA blocks blockedUser
        $this->userA->block($blockedUser);

        // UserA CAN see blocked user in search (new behavior for unarchiving)
        Livewire::test('pages::chat')
            ->set('showNewConversation', true)
            ->set('searchUser', 'Blocked')
            ->assertSee('Blocked Person');
    });

    it('prevents blocked user from searching for blocker', function (): void {
        $blockerUser = User::factory()->create(['name' => 'Blocker Person']);

        // blockerUser blocks userA
        $blockerUser->block($this->userA);

        // UserA should not be able to see the blocker in search
        Livewire::test('pages::chat')
            ->set('showNewConversation', true)
            ->set('searchUser', 'Blocker')
            ->assertDontSee('Blocker Person');
    });

    it('prevents creating conversations with blocked users', function (): void {
        $this->userB->block($this->userA);

        Livewire::test('pages::chat')
            ->call('startConversation', $this->userB->id);

        // No conversation is created when the target has blocked the user
        expect(Conversation::query()->count())->toBe(0);
    });

    it('handles blocking when conversation does not exist', function (): void {
        // No conversation exists yet
        expect(Conversation::query()->count())->toBe(0);

        // Block the user directly
        $this->userA->block($this->userB);

        // Try to start a conversation
        Livewire::test('pages::chat')
            ->call('startConversation', $this->userB->id);

        // No conversation is created with a blocked user
        expect(Conversation::query()->count())->toBe(0);
    });
});

describe('block real-time updates', function (): void {
    beforeEach(function (): void {
        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();
        $this->actingAs($this->userA);
    });

    it('updates UI when user is blocked in real-time', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        $component = Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id]);

        // Simulate receiving block event
        $component->call('handleUserBlocked', $this->userB->id);

        // Component should refresh
        $component->assertDispatched('$refresh');
    });

    it('updates UI when user is unblocked in real-time', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        $component = Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id]);

        // Simulate receiving unblock event
        $component->call('handleUserUnblocked', $this->userB->id);

        // Component should refresh
        $component->assertDispatched('$refresh');
    });

    it('broadcasts block event when blocking user', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        Event::fake();

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->call('confirmBlock');

        Event::assertDispatched(UserBlocked::class, fn ($event): bool => $event->blocker->id === $this->userA->id
            && $event->blocked->id === $this->userB->id);
    });

    it('broadcasts unblock event when unblocking user', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Event::fake();

        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->call('confirmBlock');

        Event::assertDispatched(UserUnblocked::class, fn ($event): bool => $event->unblocker->id === $this->userA->id
            && $event->unblocked->id === $this->userB->id);
    });
});

describe('blocked user status display', function (): void {
    it('shows "Not available" instead of online status when blocked', function (): void {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create(['last_seen_at' => now()->subMinutes(5)]);
        $conversation = Conversation::factory()->create([
            'user1_id' => $blocker->id,
            'user2_id' => $blocked->id,
        ]);

        // Add a message so conversation is visible
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $blocker->id,
            'content' => 'test',
        ]);

        // Blocker blocks the other user
        $blocker->block($blocked);

        // Load chat page as blocker
        $this->actingAs($blocker);
        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->assertSee('Not available')
            ->assertDontSee('Online')
            ->assertDontSee('Last seen');
    });

    it('shows "Not available" when blocked user views blocker status', function (): void {
        $blocker = User::factory()->create(['last_seen_at' => now()->subMinutes(5)]);
        $blocked = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user1_id' => $blocker->id,
            'user2_id' => $blocked->id,
        ]);

        // Add a message so conversation is visible
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $blocker->id,
            'content' => 'test',
        ]);

        // Blocker blocks the other user
        $blocker->block($blocked);

        // Load chat page as blocked user
        $this->actingAs($blocked);
        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->assertSee('Not available')
            ->assertDontSee('Online')
            ->assertDontSee('Last seen');
    });

    it('shows normal last seen status after unblocking', function (): void {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create(['last_seen_at' => now()->subMinutes(10)]);
        $conversation = Conversation::factory()->create([
            'user1_id' => $blocker->id,
            'user2_id' => $blocked->id,
        ]);

        // Add a message so conversation is visible
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $blocker->id,
            'content' => 'test',
        ]);

        // Blocker blocks then unblocks the other user
        $blocker->block($blocked);
        $blocker->unblock($blocked);

        // Load chat page as blocker
        $this->actingAs($blocker);
        Livewire::test('pages::chat', ['conversationHash' => $conversation->hash_id])
            ->assertSee('Last seen')
            ->assertDontSee('Not available');
    });
});
