<?php

declare(strict_types=1);

use App\Events\UserBlocked;
use App\Events\UserUnblocked;
use App\Livewire\Page\Chat;
use App\Models\Conversation;
use App\Models\ConversationArchive;
use App\Models\Message;
use App\Models\User;
use App\Services\UserBlockingService;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->userA = User::factory()->create();
    $this->userB = User::factory()->create();
    $this->actingAs($this->userA);
});

describe('Chat blocking UI', function (): void {
    it('shows block option in conversation dropdown menu', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->assertSee('Block User');
    });

    it('shows unblock option when user is already blocked', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->assertSee('Unblock User');
    });

    it('shows block modal when block option is clicked', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->call('openBlockModal')
            ->assertSet('showBlockModal', true)
            ->assertSee('What happens when you block someone');
    });

    it('blocks user when confirm is clicked', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->set('blockReason', 'Test reason')
            ->call('confirmBlock');

        expect($this->userA->hasBlocked($this->userB))->toBeTrue();
    });

    it('unblocks user when already blocked', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->call('confirmBlock');

        expect($this->userA->fresh()->hasBlocked($this->userB))->toBeFalse();
    });
});

describe('Conversation archiving', function (): void {
    it('does not automatically archive conversations when blocking', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        $service = new UserBlockingService;
        $service->blockUser($this->userA, $this->userB);

        expect(ConversationArchive::query()->where('conversation_id', $conversation->id)->count())->toBe(0);
    });

    it('allows manual archiving of blocked conversations', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->call('archiveConversation');

        expect($conversation->isArchivedBy($this->userA))->toBeTrue();
    });

    it('allows blocker to unarchive but prevents it when mutually blocked', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $conversation->archiveFor($this->userA);
        $this->userA->block($this->userB);

        // Blocker CAN unarchive their own archived conversation
        expect($this->userA->can('unarchive', $conversation))->toBeTrue();

        // But if the other user also blocks, then neither can unarchive
        $this->userB->block($this->userA);
        expect($this->userA->can('unarchive', $conversation))->toBeFalse();
        expect($this->userB->can('unarchive', $conversation))->toBeFalse();
    });

    it('allows unarchiving conversations when both users are not blocking', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $conversation->archiveFor($this->userA);

        expect($this->userA->can('unarchive', $conversation))->toBeTrue();
    });

    it('prevents starting conversations with blocked users through unarchiving', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $conversation->archiveFor($this->userA);
        $this->userB->block($this->userA);

        Livewire::test(Chat::class)
            ->call('startConversation', $this->userB->id)
            ->assertNoRedirect();

        // Conversation should still be archived
        expect($conversation->isArchivedBy($this->userA))->toBeTrue();
    });
});

describe('Message sending', function (): void {
    it('shows blocked message instead of input when either user blocks', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->assertSee("You can't send messages in this conversation")
            ->assertDontSee('Type a message...');
    });

    it('shows blocked message when blocked by other user', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userB->block($this->userA);

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->assertSee("You can't send messages in this conversation");
    });

    it('prevents sending messages when blocked', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->set('messageText', 'Test message')
            ->call('sendMessage');

        expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(0);
    });

    it('allows sending messages after unblocking', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);
        $this->userA->unblock($this->userB);

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->set('messageText', 'Test message')
            ->call('sendMessage');

        expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(1);
    });
});

describe('Conversation search', function (): void {
    it('excludes users who have blocked the current user from search', function (): void {
        $blockerUser = User::factory()->create(['name' => 'Blocker User']);
        $normalUser = User::factory()->create(['name' => 'Normal User']);

        $blockerUser->block($this->userA);

        Livewire::test(Chat::class)
            ->set('showNewConversation', true)
            ->set('searchUser', 'User')
            ->assertDontSee('Blocker User')
            ->assertSee('Normal User');
    });

    it('allows users to search for users they have blocked', function (): void {
        $blockedUser = User::factory()->create(['name' => 'Blocked User']);
        $normalUser = User::factory()->create(['name' => 'Normal User']);

        $this->userA->block($blockedUser);

        // Blocker CAN see blocked users in search (to unarchive conversations)
        Livewire::test(Chat::class)
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
        Livewire::test(Chat::class)
            ->set('showNewConversation', true)
            ->set('searchUser', 'Blocked')
            ->assertSee('Blocked Person');
    });

    it('prevents blocked user from searching for blocker', function (): void {
        $blockerUser = User::factory()->create(['name' => 'Blocker Person']);

        // blockerUser blocks userA
        $blockerUser->block($this->userA);

        // UserA should not be able to see the blocker in search
        Livewire::test(Chat::class)
            ->set('showNewConversation', true)
            ->set('searchUser', 'Blocker')
            ->assertDontSee('Blocker Person');
    });

    it('prevents creating conversations with blocked users', function (): void {
        $this->userB->block($this->userA);

        Livewire::test(Chat::class)
            ->call('startConversation', $this->userB->id);

        // A conversation gets created, but the user can't send messages
        expect(Conversation::query()->count())->toBe(1);

        $conversation = Conversation::query()->first();
        expect($this->userA->can('sendMessage', $conversation))->toBeFalse();
    });
});

describe('Real-time updates', function (): void {
    it('updates UI when user is blocked in real-time', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        $component = Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id]);

        // Simulate receiving block event
        $component->call('handleUserBlocked', $this->userB->id);

        // Component should refresh
        $component->assertDispatched('$refresh');
    });

    it('updates UI when user is unblocked in real-time', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        $component = Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id]);

        // Simulate receiving unblock event
        $component->call('handleUserUnblocked', $this->userB->id);

        // Component should refresh
        $component->assertDispatched('$refresh');
    });

    it('broadcasts block event when blocking user', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        Event::fake();

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->call('confirmBlock');

        Event::assertDispatched(UserBlocked::class, fn ($event): bool => $event->blocker->id === $this->userA->id
            && $event->blocked->id === $this->userB->id);
    });

    it('broadcasts unblock event when unblocking user', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);
        $this->userA->block($this->userB);

        Event::fake();

        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->call('confirmBlock');

        Event::assertDispatched(UserUnblocked::class, fn ($event): bool => $event->unblocker->id === $this->userA->id
            && $event->unblocked->id === $this->userB->id);
    });
});

describe('Edge cases', function (): void {
    it('handles blocking when conversation does not exist', function (): void {
        // No conversation exists yet
        expect(Conversation::query()->count())->toBe(0);

        // Block the user directly
        $this->userA->block($this->userB);

        // Try to start a conversation
        Livewire::test(Chat::class)
            ->call('startConversation', $this->userB->id);

        // Conversation should be created but messaging disabled
        expect(Conversation::query()->count())->toBe(1);

        $conversation = Conversation::query()->first();
        expect($this->userA->can('sendMessage', $conversation))->toBeFalse();
    });

    it('handles mutual blocking correctly', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        // Both users block each other
        $this->userA->block($this->userB);
        $this->userB->block($this->userA);

        // Neither should be able to send messages
        expect($this->userA->can('sendMessage', $conversation))->toBeFalse()
            ->and($this->userB->can('sendMessage', $conversation))->toBeFalse();

        // Neither should be able to unarchive if archived
        $conversation->archiveFor($this->userA);
        $conversation->archiveFor($this->userB);

        expect($this->userA->can('unarchive', $conversation))->toBeFalse()
            ->and($this->userB->can('unarchive', $conversation))->toBeFalse();
    });

    it('restores messaging after both users unblock', function (): void {
        $conversation = Conversation::findOrCreateBetween($this->userA, $this->userB, $this->userA);

        // Both block each other
        $this->userA->block($this->userB);
        $this->userB->block($this->userA);

        // Both unblock
        $this->userA->unblock($this->userB);
        $this->userB->unblock($this->userA);

        // Both should be able to send messages again
        expect($this->userA->can('sendMessage', $conversation))->toBeTrue()
            ->and($this->userB->can('sendMessage', $conversation))->toBeTrue();
    });
});

describe('Archived conversation with blocked users', function (): void {
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
        Livewire::test(Chat::class)
            ->set('searchUser', 'UniqueBlockedUser')
            ->call('startConversation', $blocked->id)
            ->assertSet('selectedConversation.id', $conversation->id);

        // Conversation should be unarchived
        $conversation->refresh();
        expect($conversation->isArchivedBy($blocker))->toBeFalse();
    });

    it('prevents unarchiving when both users block each other', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create(['name' => 'Mutual Block']);

        // Create conversation
        $conversation = Conversation::factory()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'test',
        ]);

        // User1 blocks user2 and archives
        $user1->block($user2);
        $conversation->archiveFor($user1);

        // User2 blocks user1
        $user2->block($user1);

        // User1 cannot unarchive due to mutual blocking
        $this->actingAs($user1);
        expect($user1->can('unarchive', $conversation))->toBeFalse();

        // User1 should NOT find user2 in search (other user blocked them)
        $searchResults = User::conversationSearch($user1, 'Mutual')->get();
        expect($searchResults)->toHaveCount(0);

        // Conversation should remain archived (cannot be unarchived due to mutual blocking)
        expect($conversation->isArchivedBy($user1))->toBeTrue();

        // Direct attempt to unarchive should be denied by policy
        expect($user1->can('unarchive', $conversation))->toBeFalse();
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
        Livewire::test(Chat::class)
            ->call('startConversation', $blocked->id)
            ->assertSet('selectedConversation.id', $conversation->id);

        expect($conversation->fresh()->isArchivedBy($blocker))->toBeFalse();
    });

    it('shows blocked users in search to allow unarchiving', function (): void {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create(['name' => 'No Archive']);

        // Create conversation but don't archive it
        $conversation = Conversation::factory()->create([
            'user1_id' => $blocker->id,
            'user2_id' => $blocked->id,
        ]);

        // Block user (conversation is not archived)
        $blocker->block($blocked);

        // Blocked user SHOULD appear in search (blocker can search for users they blocked)
        $searchResults = User::conversationSearch($blocker, 'No Archive')->get();
        expect($searchResults)->toHaveCount(1);
        expect($searchResults->first()->id)->toBe($blocked->id);
    });

    it('blocked user cannot search for blocker even with archived conversation', function (): void {
        $blocker = User::factory()->create(['name' => 'Blocker User']);
        $blocked = User::factory()->create();

        // Create conversation
        $conversation = Conversation::factory()->create([
            'user1_id' => $blocker->id,
            'user2_id' => $blocked->id,
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $blocker->id,
            'content' => 'test',
        ]);

        // Blocker blocks and archives
        $blocker->block($blocked);
        $conversation->archiveFor($blocker);

        // Also archive for blocked user
        $conversation->archiveFor($blocked);

        // Blocked user should NOT find blocker in search
        $searchResults = User::conversationSearch($blocked, 'Blocker')->get();
        expect($searchResults)->toHaveCount(0);

        // Blocked user CANNOT unarchive (they're blocked by the other user)
        $this->actingAs($blocked);
        expect($blocked->can('unarchive', $conversation))->toBeFalse();
    });
});

describe('User status display when blocked', function (): void {
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
        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
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
        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
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
        Livewire::test(Chat::class, ['conversationHash' => $conversation->hash_id])
            ->assertSee('Last seen')
            ->assertDontSee('Not available');
    });
});
