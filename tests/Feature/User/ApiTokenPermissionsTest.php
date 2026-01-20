<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

describe('API token permissions', function (): void {
    it('can update api token permissions', function (): void {
        $this->actingAs($user = User::factory()->create());

        $token = $user->tokens()->create([
            'name' => 'Test Token',
            'token' => Str::random(40),
            'abilities' => ['create', 'read'],
        ]);

        Livewire::test('profile.api-token-manager')
            ->set(['managingPermissionsFor' => $token])
            ->set(['updateApiTokenForm' => [
                'permissions' => [
                    'delete',
                    'missing-permission',
                ],
            ]])
            ->call('updateApiToken');

        expect($user->fresh()->tokens->first())
            ->can('delete')->toBeTrue()
            ->can('read')->toBeTrue()
            ->can('missing-permission')->toBeFalse();
    });

    it('always maintains the read permission when updating a token', function (): void {
        $this->actingAs($user = User::factory()->create());

        $token = $user->tokens()->create([
            'name' => 'Test Token',
            'token' => Str::random(40),
            'abilities' => ['read', 'create'],
        ]);

        Livewire::test('profile.api-token-manager')
            ->set(['managingPermissionsFor' => $token])
            ->set(['updateApiTokenForm' => [
                'permissions' => [
                    'update',
                ],
            ]])
            ->call('updateApiToken');

        expect($user->fresh()->tokens->first())
            ->can('update')->toBeTrue()
            ->can('read')->toBeTrue();
    });
});
