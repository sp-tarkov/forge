<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
