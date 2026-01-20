<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

describe('API token creation', function (): void {
    it('can create api tokens', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test('profile.api-token-manager')
            ->set(['createApiTokenForm' => [
                'name' => 'Test Token',
                'permissions' => [
                    'read',
                    'update',
                ],
            ]])
            ->call('createApiToken');

        expect($user->fresh()->tokens)->toHaveCount(1);
        expect($user->fresh()->tokens->first())
            ->name->toEqual('Test Token')
            ->can('read')->toBeTrue()
            ->can('delete')->toBeFalse();
    });

    it('always includes the read permission when creating a token', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test('profile.api-token-manager')
            ->set(['createApiTokenForm' => [
                'name' => 'Test Token',
                'permissions' => [
                    'create',
                ],
            ]])
            ->call('createApiToken');

        expect($user->fresh()->tokens)->toHaveCount(1);
        expect($user->fresh()->tokens->first())
            ->can('create')->toBeTrue()
            ->can('read')->toBeTrue();
    });
});
