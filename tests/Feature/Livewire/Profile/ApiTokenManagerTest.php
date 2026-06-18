<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

describe('page access', function (): void {
    it('renders the API tokens page', function (): void {
        $this->actingAs(User::factory()->create())
            ->get('/user/api-tokens')
            ->assertOk();
    });

    it('redirects guests from API tokens to login', function (): void {
        $this->get('/user/api-tokens')->assertRedirect('/login');
    });
});

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
