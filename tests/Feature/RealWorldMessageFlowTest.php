<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows unread badge in complete user flow from conversation creation to first message', function (): void {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    // Step 1: Alice opens new conversation modal and starts conversation with Bob
    $aliceNav = Livewire::actingAs($alice)->test('navigation-chat');
    $aliceNav->call('openNewConversationModal')
        ->set('searchUser', 'Bob')
        ->assertSee('Bob')
        ->call('startConversation', $bob->id);

    // Get the created conversation
    $conversation = Conversation::query()->where('user1_id', min($alice->id, $bob->id))
        ->where('user2_id', max($alice->id, $bob->id))
        ->first();

    expect($conversation)->not->toBeNull();
    expect($conversation->created_by)->toBe($alice->id);

    // At this point, conversation exists but has no messages
    expect($conversation->messages()->count())->toBe(0);
    expect($conversation->last_message_id)->toBeNull();

    // Bob should NOT see the conversation yet (no messages)
    $bobNav = Livewire::actingAs($bob)->test('navigation-chat');
    $bobNav->assertDontSee('Alice');

    // Step 2: Alice sends the first message
    $aliceChat = Livewire::actingAs($alice)->test('pages::chat', ['conversationHash' => $conversation->hash_id]);
    $aliceChat->set('messageText', 'Hello Bob!')
        ->call('sendMessage')
        ->assertSet('messageText', ''); // Message input should be cleared

    // Verify message was created and conversation was updated
    $conversation->refresh();
    expect($conversation->messages()->count())->toBe(1);
    expect($conversation->last_message_id)->not->toBeNull();
    expect($conversation->last_message_at)->not->toBeNull();

    // Step 3: Bob refreshes and should now see the conversation with unread badge
    $bobNavRefreshed = Livewire::actingAs($bob)->test('navigation-chat');
    $bobNavRefreshed->assertSee('Alice')  // Should see Alice's name
        ->assertSee('Hello Bob!')  // Should see message preview
        ->assertSee('1');  // Should see unread count badge

    // Verify unread count is correct
    expect($conversation->getUnreadCountForUser($bob))->toBe(1);

    // Step 4: Bob opens the conversation
    $bobChat = Livewire::actingAs($bob)->test('pages::chat', ['conversationHash' => $conversation->hash_id]);
    $bobChat->assertSee('Hello Bob!')
        ->assertSee('Alice');

    // After Bob views the conversation, unread count should be 0
    expect($conversation->getUnreadCountForUser($bob))->toBe(0);

    // Verify the navigation component gets correct unread count
    $bobNavAfterRead = Livewire::actingAs($bob)->test('navigation-chat');
    // Check via rendered output since the unread count is computed inline
    $bobNavAfterRead->assertDontSee('class="absolute -top-1 -right-1 flex h-4 w-4');
});

it('correctly handles multiple conversations with different unread states', function (): void {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);
    $charlie = User::factory()->create(['name' => 'Charlie']);

    // Alice creates conversations with Bob and Charlie
    $convWithBob = Conversation::findOrCreateBetween($alice, $bob, $alice);
    $convWithCharlie = Conversation::findOrCreateBetween($alice, $charlie, $alice);

    // Alice sends messages to both
    $convWithBob->messages()->create([
        'user_id' => $alice->id,
        'content' => 'Hello Bob!',
    ]);

    $convWithCharlie->messages()->create([
        'user_id' => $alice->id,
        'content' => 'Hello Charlie!',
    ]);

    // Bob checks his navigation - should see 1 unread conversation
    $bobNav = Livewire::actingAs($bob)->test('navigation-chat');
    $bobNav->assertSee('Alice')
        ->assertSee('1');  // Unread badge

    // Charlie checks his navigation - should also see 1 unread conversation
    $charlieNav = Livewire::actingAs($charlie)->test('navigation-chat');
    $charlieNav->assertSee('Alice')
        ->assertSee('1');  // Unread badge

    // Bob reads his conversation
    Livewire::actingAs($bob)
        ->test('pages::chat', ['conversationHash' => $convWithBob->hash_id]);

    // Bob's navigation should no longer show unread badge
    $bobNavAfter = Livewire::actingAs($bob)->test('navigation-chat');
    // The unread badge should not be visible for Bob after reading
    $bobNavAfter->assertDontSee('bg-red-600');

    // Charlie still has unread - check via rendered badge
    $charlieNavStill = Livewire::actingAs($charlie)->test('navigation-chat');
    $charlieNavStill->assertSee('bg-red-600');
});
