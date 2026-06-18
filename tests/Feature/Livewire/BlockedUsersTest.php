<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

describe('blocked users list', function (): void {
    it('displays the blocked users with their reasons', function (): void {
        $currentUser = User::factory()->create();
        $blockedUser1 = User::factory()->create(['name' => 'Blocked User 1']);
        $blockedUser2 = User::factory()->create(['name' => 'Blocked User 2']);

        $currentUser->block($blockedUser1, 'Reason 1');
        $currentUser->block($blockedUser2, 'Reason 2');

        $this->actingAs($currentUser);

        Livewire::test('blocked-users')
            ->assertSee('Blocked User 1')
            ->assertSee('Blocked User 2')
            ->assertSee('Reason 1')
            ->assertSee('Reason 2');
    });

    it('allows unblocking users from the blocked users list', function (): void {
        $currentUser = User::factory()->create();
        $blockedUser = User::factory()->create();

        $currentUser->block($blockedUser);

        $this->actingAs($currentUser);

        Livewire::test('blocked-users')
            ->assertSee($blockedUser->name)
            ->call('unblockUser', $blockedUser->id)
            ->assertDispatched('user-unblocked');

        expect($currentUser->fresh()->hasBlocked($blockedUser))->toBeFalse();
    });
});
