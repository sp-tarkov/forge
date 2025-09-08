<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
