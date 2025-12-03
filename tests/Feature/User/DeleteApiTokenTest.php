<?php

declare(strict_types=1);

use App\Livewire\Profile\ApiTokenManager;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;
use Livewire\Livewire;

describe('API token deletion', function (): void {
    it('can delete api tokens', function (): void {
        if (Features::hasTeamFeatures()) {
            $this->actingAs($user = User::factory()->withPersonalTeam()->create());
        } else {
            $this->actingAs($user = User::factory()->create());
        }

        $token = $user->tokens()->create([
            'name' => 'Test Token',
            'token' => Str::random(40),
            'abilities' => ['create', 'read'],
        ]);

        Livewire::test(ApiTokenManager::class)
            ->set(['apiTokenIdBeingDeleted' => $token->id])
            ->call('deleteApiToken');

        expect($user->fresh()->tokens)->toHaveCount(0);
    })->skip(fn (): bool => ! Features::hasApiFeatures(), 'API support is not enabled.');

    it('handles deleting an already-deleted token gracefully', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(ApiTokenManager::class)
            ->set(['apiTokenIdBeingDeleted' => 99999])
            ->call('deleteApiToken')
            ->assertSet('confirmingApiTokenDeletion', false);

        expect($user->fresh()->tokens)->toHaveCount(0);
    })->skip(fn (): bool => ! Features::hasApiFeatures(), 'API support is not enabled.');
});
