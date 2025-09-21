<?php

declare(strict_types=1);

use App\Livewire\NavigationChat;
use App\Livewire\Page\Chat;
use App\Models\Conversation;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('displays the search input with correct binding in new conversation modal', function (): void {
    // Create other users to search for
    $otherUser = User::factory()->create(['name' => 'John Doe']);

    Livewire::test(Chat::class)
        ->assertSet('searchUser', '') // Initially empty
        ->set('showNewConversation', true) // Open the modal
        ->assertSee('Start typing to search for users')
        ->set('searchUser', 'John')
        ->assertSet('searchUser', 'John')
        ->assertDontSee('No users found matching "searchUser"') // Should not show literal "searchUser"
        ->assertSee('John Doe');
});

it('shows no results message with actual search term when no users found', function (): void {
    Livewire::test(Chat::class)
        ->assertSet('searchUser', '')
        ->set('showNewConversation', true) // Open the modal
        ->set('searchUser', 'NonexistentUser')
        ->assertSet('searchUser', 'NonexistentUser')
        ->assertSee('No users found matching')
        ->assertSee('NonexistentUser'); // Check separately due to potential HTML escaping
});

it('shows empty state message when search input is empty', function (): void {
    Livewire::test(Chat::class)
        ->assertSet('searchUser', '')
        ->set('showNewConversation', true) // Open the modal
        ->assertSee('Start typing to search for users')
        ->assertDontSee('No users found matching');
});

it('clears search results when search input is cleared', function (): void {
    $otherUser = User::factory()->create(['name' => 'Jane Smith']);

    Livewire::test(Chat::class)
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

    Livewire::test(Chat::class)
        ->set('showNewConversation', true) // Open the modal
        ->set('searchUser', 'Mod Creator')
        ->assertSee('Mod Creator')
        ->assertSee('3 mods');
});

it('excludes users who have blocked the current user from search', function (): void {
    $blockerUser = User::factory()->create(['name' => 'Blocker User']);
    $normalUser = User::factory()->create(['name' => 'Normal User']);

    // blockerUser blocks the current user
    $blockerUser->block($this->user);

    Livewire::test(Chat::class)
        ->set('showNewConversation', true)
        ->set('searchUser', 'User')
        ->assertDontSee('Blocker User')
        ->assertSee('Normal User');
});

it('excludes mutually blocked users from search', function (): void {
    $mutuallyBlockedUser = User::factory()->create(['name' => 'Mutually Blocked']);
    $normalUser = User::factory()->create(['name' => 'Normal Person']);

    // Both users block each other
    $this->user->block($mutuallyBlockedUser);
    $mutuallyBlockedUser->block($this->user);

    Livewire::test(Chat::class)
        ->set('showNewConversation', true)
        ->set('searchUser', 'lock') // Should match "Blocked" in the name
        ->assertDontSee('Mutually Blocked')
        ->set('searchUser', 'Normal')
        ->assertSee('Normal Person');
});

it('prevents starting conversations with users who blocked current user', function (): void {
    $blockerUser = User::factory()->create(['name' => 'Blocker User']);

    // blockerUser blocks the current user
    $blockerUser->block($this->user);

    Livewire::test(NavigationChat::class)
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

    Livewire::test(NavigationChat::class)
        ->call('startConversation', $blockedUser->id)
        ->assertSet('showNewConversation', false)
        ->assertNoRedirect();

    // Verify no conversation was created
    expect(Conversation::query()->count())->toBe(0);
});

it('allows starting conversations with non-blocked users', function (): void {
    $normalUser = User::factory()->create(['name' => 'Normal User']);

    Livewire::test(NavigationChat::class)
        ->call('startConversation', $normalUser->id)
        ->assertRedirect()
        ->assertSet('showNewConversation', false);

    // No error flash message should be set
    expect(session('flash_notification.level'))->not->toBe('error');
});
