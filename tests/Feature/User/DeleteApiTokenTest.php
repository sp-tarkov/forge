<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

describe('API token deletion', function (): void {
    it('can delete api tokens', function (): void {
        $this->actingAs($user = User::factory()->create());

        $token = $user->tokens()->create([
            'name' => 'Test Token',
            'token' => Str::random(40),
            'abilities' => ['create', 'read'],
        ]);

        Livewire::test('profile.api-token-manager')
            ->set(['apiTokenIdBeingDeleted' => $token->id])
            ->call('deleteApiToken');

        expect($user->fresh()->tokens)->toHaveCount(0);
    });

    it('handles deleting an already-deleted token gracefully', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test('profile.api-token-manager')
            ->set(['apiTokenIdBeingDeleted' => 99999])
            ->call('deleteApiToken')
            ->assertSet('confirmingApiTokenDeletion', false);

        expect($user->fresh()->tokens)->toHaveCount(0);
    });
});
