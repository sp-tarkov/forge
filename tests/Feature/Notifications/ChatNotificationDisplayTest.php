<?php

declare(strict_types=1);

use App\Livewire\NotificationCenter;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('displays chat message notifications with correct details', function (): void {
    $recipient = User::factory()->create();
    $sender = User::factory()->create(['name' => 'John Doe']);

    $conversation = Conversation::factory()->create([
        'user1_id' => $recipient->id,
        'user2_id' => $sender->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'content' => 'Hello! This is a test message that should appear in notifications.',
    ]);

    // Create notification data
    $recipient->notify(new NewChatMessageNotification($conversation, collect([$message])));

    Livewire::actingAs($recipient)
        ->test(NotificationCenter::class)
        ->assertSee('John Doe')
        ->assertSee('sent you a')
        ->assertSee('new message')
        ->assertSee('Hello! This is a test message that should appear in notifications.')
        ->assertSeeHtml('bg-purple-100'); // Chat notification icon background color
});

it('displays multiple message count correctly', function (): void {
    $recipient = User::factory()->create();
    $sender = User::factory()->create(['name' => 'Jane Smith']);

    $conversation = Conversation::factory()->create([
        'user1_id' => $recipient->id,
        'user2_id' => $sender->id,
    ]);

    $messages = Message::factory()->count(3)->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    // Create notification with multiple messages
    $recipient->notify(new NewChatMessageNotification($conversation, $messages));

    Livewire::actingAs($recipient)
        ->test(NotificationCenter::class)
        ->assertSee('Jane Smith')
        ->assertSee('sent you')
        ->assertSee('3 new messages')
        ->assertSee(Str::limit($messages->last()->content, 150));
});

it('shows correct unread state for chat notifications', function (): void {
    $recipient = User::factory()->create();
    $sender = User::factory()->create();

    $conversation = Conversation::factory()->create([
        'user1_id' => $recipient->id,
        'user2_id' => $sender->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    $recipient->notify(new NewChatMessageNotification($conversation, collect([$message])));
    $notificationId = $recipient->notifications()->first()->id;

    $component = Livewire::actingAs($recipient)
        ->test(NotificationCenter::class);

    // Check initial unread state - blue left border indicates unread
    $component->assertSeeHtml('bg-blue-500') // Unread indicator bar
        ->assertSee('Mark read');

    // Mark as read
    $component->call('markAsRead', $notificationId);

    // Verify the notification is marked as read
    expect($recipient->notifications()->find($notificationId)->read_at)->not->toBeNull();
});

it('redirects to conversation when notification is reviewed', function (): void {
    $recipient = User::factory()->create();
    $sender = User::factory()->create();

    $conversation = Conversation::factory()->create([
        'user1_id' => $recipient->id,
        'user2_id' => $sender->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    $recipient->notify(new NewChatMessageNotification($conversation, collect([$message])));
    $notificationId = $recipient->notifications()->first()->id;

    Livewire::actingAs($recipient)
        ->test(NotificationCenter::class)
        ->call('reviewNotification', $notificationId)
        ->assertRedirect($conversation->url);
});

it('correctly handles notifications when sender name is provided', function (): void {
    $recipient = User::factory()->create();
    $sender = User::factory()->create(['name' => 'John Smith']);

    $conversation = Conversation::factory()->create([
        'user1_id' => $recipient->id,
        'user2_id' => $sender->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'content' => 'Message from John Smith',
    ]);

    // Create notification
    $recipient->notify(new NewChatMessageNotification($conversation, collect([$message])));

    Livewire::actingAs($recipient)
        ->test(NotificationCenter::class)
        ->assertSee('John Smith')
        ->assertSee('sent you a')
        ->assertSee('new message')
        ->assertSee('Message from John Smith')
        ->assertSeeHtml('bg-purple-100'); // Chat notification icon background
});
