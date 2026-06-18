<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

describe('block button', function (): void {
    it('displays and handles block and unblock actions correctly', function (): void {
        $currentUser = User::factory()->create();
        $targetUser = User::factory()->create();

        $this->actingAs($currentUser);

        Livewire::test('block-button', ['user' => $targetUser])
            ->assertSee('Block User')
            ->call('toggleBlockModal')
            ->assertSet('showModal', true)
            ->set('blockReason', 'Test reason')
            ->call('confirmBlock')
            ->assertDispatched('user-blocked');

        expect($currentUser->fresh()->hasBlocked($targetUser))->toBeTrue();

        Livewire::test('block-button', ['user' => $targetUser])
            ->assertSee('Unblock')
            ->call('toggleBlockModal')
            ->call('confirmBlock')
            ->assertDispatched('user-unblocked');

        expect($currentUser->fresh()->hasBlocked($targetUser))->toBeFalse();
    });
});
