<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\ConversationSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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
