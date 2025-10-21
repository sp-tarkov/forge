<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Livewire\Livewire;

describe('account deletion', function (): void {
    it('can delete user accounts', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'password')
            ->call('deleteUser');

        expect($user->fresh())->toBeNull();
    })->skip(fn (): bool => ! Features::hasAccountDeletionFeatures(), 'Account deletion is not enabled.');

    it('requires correct password before deletion', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        expect($user->fresh())->not->toBeNull();
    })->skip(fn (): bool => ! Features::hasAccountDeletionFeatures(), 'Account deletion is not enabled.');

    it('prevents banned users from accessing their profile', function (): void {
        $user = User::factory()->create();
        $user->ban();

        $response = $this->actingAs($user)->get('/user/profile');

        $response->assertForbidden();

        expect($user->fresh())->not->toBeNull();
    })->skip(fn (): bool => ! Features::hasAccountDeletionFeatures(), 'Account deletion is not enabled.');

    it('prevents banned users from accessing any page on the site', function (): void {
        $user = User::factory()->create();
        $user->ban();

        // Banned users CANNOT access ANY pages (public or authenticated)
        $this->actingAs($user)->get('/')->assertForbidden();
        $this->actingAs($user)->get('/mods')->assertForbidden();
        $this->actingAs($user)->get('/privacy-policy')->assertForbidden();
        $this->actingAs($user)->get('/terms-of-service')->assertForbidden();
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/user/profile')->assertForbidden();
    });
});
