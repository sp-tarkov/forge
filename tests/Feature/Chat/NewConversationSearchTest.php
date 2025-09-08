<?php

declare(strict_types=1);

use App\Livewire\Page\Chat;
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
