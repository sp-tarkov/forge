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
});
