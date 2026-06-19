<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

beforeEach(function (): void {
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();
    $this->user3 = User::factory()->create();

    // Create a conversation between user1 and user2
    $this->conversation = Conversation::query()->create([
        'user1_id' => $this->user1->id,
        'user2_id' => $this->user2->id,
        'created_by' => $this->user1->id,
    ]);

    // Create a message so both users can see the conversation
    $message = Message::query()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => 'Test message',
    ]);

    $this->conversation->update([
        'last_message_id' => $message->id,
        'last_message_at' => now(),
    ]);
});

describe('viewAny', function (): void {
    it('allows any authenticated user to view their conversations list', function (): void {
        expect($this->user1->can('viewAny', Conversation::class))->toBeTrue();
        expect($this->user2->can('viewAny', Conversation::class))->toBeTrue();
        expect($this->user3->can('viewAny', Conversation::class))->toBeTrue();
    });
});

describe('view', function (): void {
    it('allows users who are part of the conversation to view it', function (): void {
        expect($this->user1->can('view', $this->conversation))->toBeTrue();
        expect($this->user2->can('view', $this->conversation))->toBeTrue();
    });

    it('denies users who are not part of the conversation from viewing it', function (): void {
        expect($this->user3->can('view', $this->conversation))->toBeFalse();
    });
});

describe('create', function (): void {
    it('allows any authenticated user to create a conversation', function (): void {
        expect($this->user1->can('create', Conversation::class))->toBeTrue();
        expect($this->user2->can('create', Conversation::class))->toBeTrue();
        expect($this->user3->can('create', Conversation::class))->toBeTrue();
    });
});

describe('update', function (): void {
    it('allows users who are part of the conversation to update it', function (): void {
        expect($this->user1->can('update', $this->conversation))->toBeTrue();
        expect($this->user2->can('update', $this->conversation))->toBeTrue();
    });

    it('denies users who are not part of the conversation from updating it', function (): void {
        expect($this->user3->can('update', $this->conversation))->toBeFalse();
    });
});

describe('delete', function (): void {
    it('allows users who are part of the conversation to delete it', function (): void {
        expect($this->user1->can('delete', $this->conversation))->toBeTrue();
        expect($this->user2->can('delete', $this->conversation))->toBeTrue();
    });

    it('denies users who are not part of the conversation from deleting it', function (): void {
        expect($this->user3->can('delete', $this->conversation))->toBeFalse();
    });
});

describe('restore', function (): void {
    it('denies all users from restoring conversations', function (): void {
        expect($this->user1->can('restore', $this->conversation))->toBeFalse();
        expect($this->user2->can('restore', $this->conversation))->toBeFalse();
        expect($this->user3->can('restore', $this->conversation))->toBeFalse();
    });
});

describe('forceDelete', function (): void {
    it('denies all users from permanently deleting conversations', function (): void {
        expect($this->user1->can('forceDelete', $this->conversation))->toBeFalse();
        expect($this->user2->can('forceDelete', $this->conversation))->toBeFalse();
        expect($this->user3->can('forceDelete', $this->conversation))->toBeFalse();
    });
});

describe('sendMessage', function (): void {
    it('allows users who are part of the conversation to send messages', function (): void {
        expect($this->user1->can('sendMessage', $this->conversation))->toBeTrue();
        expect($this->user2->can('sendMessage', $this->conversation))->toBeTrue();
    });

    it('denies users who are not part of the conversation from sending messages', function (): void {
        expect($this->user3->can('sendMessage', $this->conversation))->toBeFalse();
    });
});

it('ensures conversation security between different user pairs', function (): void {
    // Create another conversation between user1 and user3
    $conversation2 = Conversation::query()->create([
        'user1_id' => $this->user1->id,
        'user2_id' => $this->user3->id,
        'created_by' => $this->user1->id,
    ]);

    // Create a message so both users can see
    $message2 = Message::query()->create([
        'conversation_id' => $conversation2->id,
        'user_id' => $this->user1->id,
        'content' => 'Test message 2',
    ]);

    $conversation2->update([
        'last_message_id' => $message2->id,
        'last_message_at' => now(),
    ]);

    // User1 can view both conversations
    expect($this->user1->can('view', $this->conversation))->toBeTrue();
    expect($this->user1->can('view', $conversation2))->toBeTrue();

    // User2 can only view the first conversation
    expect($this->user2->can('view', $this->conversation))->toBeTrue();
    expect($this->user2->can('view', $conversation2))->toBeFalse();

    // User3 can only view the second conversation
    expect($this->user3->can('view', $this->conversation))->toBeFalse();
    expect($this->user3->can('view', $conversation2))->toBeTrue();
});

it('handles conversation with same user IDs correctly', function (): void {
    // Test edge case where user IDs might be in different order
    $conversation3 = Conversation::query()->create([
        'user1_id' => $this->user2->id,
        'user2_id' => $this->user1->id,
        'created_by' => $this->user2->id,
    ]);

    // Create a message so both users can see
    $message3 = Message::query()->create([
        'conversation_id' => $conversation3->id,
        'user_id' => $this->user2->id,
        'content' => 'Test message 3',
    ]);

    $conversation3->update([
        'last_message_id' => $message3->id,
        'last_message_at' => now(),
    ]);

    // Both users should still be able to view the conversation
    expect($this->user1->can('view', $conversation3))->toBeTrue();
    expect($this->user2->can('view', $conversation3))->toBeTrue();
    expect($this->user3->can('view', $conversation3))->toBeFalse();
});

describe('first-message visibility', function (): void {
    it('enforces conversation visibility in the policy', function (): void {
        $creator = User::factory()->create(['email_verified_at' => now()]);
        $recipient = User::factory()->create(['email_verified_at' => now()]);

        // Create conversation with the creator as creator
        $conversation = Conversation::findOrCreateBetween($creator, $recipient, $creator);

        // Creator can view
        expect($creator->can('view', $conversation))->toBeTrue();

        // Non-creator cannot view without messages
        expect($recipient->can('view', $conversation))->toBeFalse();

        // Send message
        $message = $conversation->messages()->create([
            'user_id' => $creator->id,
            'content' => 'Hello!',
        ]);
        $conversation->update([
            'last_message_id' => $message->id,
            'last_message_at' => now(),
        ]);

        // Now non-creator can view
        expect($recipient->can('view', $conversation))->toBeTrue();
    });

    it('allows both participants to send messages regardless of visibility', function (): void {
        $creator = User::factory()->create(['email_verified_at' => now()]);
        $recipient = User::factory()->create(['email_verified_at' => now()]);

        // Create conversation with the creator as creator
        $conversation = Conversation::findOrCreateBetween($creator, $recipient, $creator);

        // Both users can send messages (even if recipient can't see the conversation yet)
        expect($creator->can('sendMessage', $conversation))->toBeTrue();
        expect($recipient->can('sendMessage', $conversation))->toBeTrue();
    });
});

describe('blocking authorization', function (): void {
    beforeEach(function (): void {
        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();
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

    it('shows blocked users in search to allow unarchiving', function (): void {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create(['name' => 'No Archive']);

        // Create conversation but don't archive it
        Conversation::factory()->create([
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
