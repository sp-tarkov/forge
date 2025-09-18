<?php

declare(strict_types=1);

use App\Jobs\ProcessChatMessageNotification;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('dispatches chat notification job when a new message is created', function (): void {
    Queue::fake();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conversation = Conversation::factory()->create([
        'user1_id' => $user1->id,
        'user2_id' => $user2->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $user1->id,
        'content' => 'Hello, how are you?',
    ]);

    Queue::assertPushed(ProcessChatMessageNotification::class, fn ($job): bool => $job->message->id === $message->id);
});

it('sends email notification for unread messages after 5 minutes', function (): void {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create([
        'email_chat_notifications_enabled' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'user1_id' => $sender->id,
        'user2_id' => $recipient->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'content' => 'Test message for notification',
    ]);

    // Manually run the job (simulating after 5 minutes)
    $job = new ProcessChatMessageNotification($message);
    $job->handle();

    Notification::assertSentTo(
        [$recipient],
        NewChatMessageNotification::class,
        fn ($notification): bool => $notification->conversation->id === $conversation->id
            && $notification->unreadMessages->contains('id', $message->id)
    );
});

it('does not send notification if message is read before 5 minutes', function (): void {
    Notification::fake();
    Queue::fake(); // Prevent automatic job dispatch

    $sender = User::factory()->create();
    $recipient = User::factory()->create([
        'email_chat_notifications_enabled' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'user1_id' => $sender->id,
        'user2_id' => $recipient->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    // Mark message as read before running the job
    $message->markAsReadBy($recipient);

    // Manually run the job (simulating after 5 minutes)
    $job = new ProcessChatMessageNotification($message);
    $job->handle();

    Notification::assertNotSentTo([$recipient], NewChatMessageNotification::class);
});

it('does not send notification if user has disabled chat notifications', function (): void {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create([
        'email_chat_notifications_enabled' => false,
    ]);

    $conversation = Conversation::factory()->create([
        'user1_id' => $sender->id,
        'user2_id' => $recipient->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    $job = new ProcessChatMessageNotification($message);
    $job->handle();

    Notification::assertNotSentTo([$recipient], NewChatMessageNotification::class);
});

it('batches multiple unread messages in a single notification', function (): void {
    Notification::fake();
    Queue::fake(); // Prevent automatic job dispatch

    $sender = User::factory()->create();
    $recipient = User::factory()->create([
        'email_chat_notifications_enabled' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'user1_id' => $sender->id,
        'user2_id' => $recipient->id,
    ]);

    // Create multiple messages
    $messages = Message::factory()->count(3)->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    // Manually run the job for any message (it will batch all unread)
    $job = new ProcessChatMessageNotification($messages->first());
    $job->handle();

    Notification::assertSentTo(
        [$recipient],
        NewChatMessageNotification::class,
        fn ($notification): bool => $notification->unreadMessages->count() === 3
    );
});

it('does not send duplicate notifications within 5 minutes', function (): void {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create([
        'email_chat_notifications_enabled' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'user1_id' => $sender->id,
        'user2_id' => $recipient->id,
    ]);

    $message1 = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    // First notification
    $job1 = new ProcessChatMessageNotification($message1);
    $job1->handle();

    // Create second message immediately after
    $message2 = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    // Try to send second notification
    $job2 = new ProcessChatMessageNotification($message2);
    $job2->handle();

    // Should only send one notification
    Notification::assertSentToTimes($recipient, NewChatMessageNotification::class, 1);
});

it('respects conversation-specific notification preferences', function (): void {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create([
        'email_chat_notifications_enabled' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'user1_id' => $sender->id,
        'user2_id' => $recipient->id,
    ]);

    // Disable notifications for this specific conversation
    $conversation->subscriptions()->create([
        'user_id' => $recipient->id,
        'notifications_enabled' => false,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    $job = new ProcessChatMessageNotification($message);
    $job->handle();

    Notification::assertNotSentTo([$recipient], NewChatMessageNotification::class);
});

it('allows toggling conversation notification preferences', function (): void {
    $user = User::factory()->create([
        'email_chat_notifications_enabled' => true,
    ]);
    $otherUser = User::factory()->create();

    $conversation = Conversation::factory()->create([
        'user1_id' => $user->id,
        'user2_id' => $otherUser->id,
    ]);

    // Initially should use global preference (true)
    expect($conversation->isNotificationEnabledForUser($user))->toBeTrue();

    // Toggle off
    $isEnabled = $conversation->toggleNotificationForUser($user);
    expect($isEnabled)->toBeFalse();
    expect($conversation->isNotificationEnabledForUser($user))->toBeFalse();

    // Toggle back on
    $isEnabled = $conversation->toggleNotificationForUser($user);
    expect($isEnabled)->toBeTrue();
    expect($conversation->isNotificationEnabledForUser($user))->toBeTrue();
});

it('uses global preference when no conversation-specific preference exists', function (): void {
    $userWithGlobalEnabled = User::factory()->create([
        'email_chat_notifications_enabled' => true,
    ]);
    $userWithGlobalDisabled = User::factory()->create([
        'email_chat_notifications_enabled' => false,
    ]);
    $otherUser = User::factory()->create();

    $conversation = Conversation::factory()->create([
        'user1_id' => $otherUser->id,
        'user2_id' => $userWithGlobalEnabled->id,
    ]);

    // Should use global preferences when no specific preference exists
    expect($conversation->isNotificationEnabledForUser($userWithGlobalEnabled))->toBeTrue();

    $conversation2 = Conversation::factory()->create([
        'user1_id' => $otherUser->id,
        'user2_id' => $userWithGlobalDisabled->id,
    ]);

    expect($conversation2->isNotificationEnabledForUser($userWithGlobalDisabled))->toBeFalse();
});

it('creates proper notification log entry', function (): void {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create([
        'email_chat_notifications_enabled' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'user1_id' => $sender->id,
        'user2_id' => $recipient->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    $job = new ProcessChatMessageNotification($message);
    $job->handle();

    $log = NotificationLog::query()
        ->where('user_id', $recipient->id)
        ->where('notifiable_type', Conversation::class)
        ->where('notifiable_id', $conversation->id)
        ->where('notification_class', NewChatMessageNotification::class)
        ->first();

    expect($log)->not->toBeNull();
});

it('generates correct URLs in email', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create([
        'email_chat_notifications_enabled' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'user1_id' => $sender->id,
        'user2_id' => $recipient->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'content' => 'Test message',
    ]);

    $notification = new NewChatMessageNotification($conversation, collect([$message]));
    $mailMessage = $notification->toMail($recipient);

    // Check action button goes to conversation
    expect($mailMessage->actionUrl)->toBe($conversation->url);
    expect($mailMessage->actionText)->toBe('View Conversation');

    // Check unsubscribe URL is in the content
    $expectedUnsubscribeUrl = URL::signedRoute('chat.unsubscribe', [
        'user' => $recipient->id,
        'conversation' => $conversation->hash_id,
    ]);

    $foundUnsubscribe = false;
    foreach ($mailMessage->outroLines as $line) {
        if (str_contains((string) $line, $expectedUnsubscribeUrl)) {
            $foundUnsubscribe = true;
            break;
        }
    }

    expect($foundUnsubscribe)->toBeTrue();
});

it('unarchives conversation when new message is sent', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = Conversation::factory()->create([
        'user1_id' => $user1->id,
        'user2_id' => $user2->id,
    ]);

    // Archive the conversation for both users
    $conversation->archiveFor($user1);
    $conversation->archiveFor($user2);

    expect($conversation->isArchivedBy($user1))->toBeTrue();
    expect($conversation->isArchivedBy($user2))->toBeTrue();

    // Send a new message
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $user1->id,
    ]);

    // Check that conversation is unarchived
    expect($conversation->fresh()->isArchivedBy($user1))->toBeFalse();
    expect($conversation->fresh()->isArchivedBy($user2))->toBeFalse();
});

it('allows user to unsubscribe from chat notifications', function (): void {
    $user = User::factory()->create([
        'email_chat_notifications_enabled' => true,
    ]);
    $otherUser = User::factory()->create();

    $conversation = Conversation::factory()->create([
        'user1_id' => $user->id,
        'user2_id' => $otherUser->id,
    ]);

    $signedUrl = URL::temporarySignedRoute(
        'chat.unsubscribe',
        now()->addHour(),
        ['user' => $user->id, 'conversation' => $conversation->hash_id]
    );

    $response = $this->get($signedUrl);

    $response->assertRedirect(route('chat', $conversation->hash_id));

    // Check conversation-specific subscription was set to false
    $subscription = $conversation->subscriptions()
        ->where('user_id', $user->id)
        ->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->notifications_enabled)->toBeFalse();

    // Global preference should remain unchanged
    expect($user->fresh()->email_chat_notifications_enabled)->toBeTrue();
});

it('rejects unsubscribe with invalid signature', function (): void {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create([
        'user1_id' => $user->id,
        'user2_id' => User::factory()->create()->id,
    ]);

    $response = $this->get(route('chat.unsubscribe', [
        'user' => $user->id,
        'conversation' => $conversation->hash_id,
    ]));

    $response->assertForbidden();
});

it('does not send notification if message is deleted', function (): void {
    Notification::fake();
    Queue::fake(); // Prevent automatic job dispatch

    $sender = User::factory()->create();
    $recipient = User::factory()->create([
        'email_chat_notifications_enabled' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'user1_id' => $sender->id,
        'user2_id' => $recipient->id,
    ]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    // Delete the message before job runs
    $message->delete();

    $job = new ProcessChatMessageNotification($message);
    $job->handle();

    Notification::assertNotSentTo([$recipient], NewChatMessageNotification::class);
});
