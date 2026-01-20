<?php

declare(strict_types=1);

use App\Livewire\Profile\UpdatePasswordForm;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

describe('password update', function (): void {
    it('can update password', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdatePasswordForm::class)
            ->set('state', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->call('updatePassword');

        expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
    });

    it('requires correct current password', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdatePasswordForm::class)
            ->set('state', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);

        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    });

    it('requires new passwords to match', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdatePasswordForm::class)
            ->set('state', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'wrong-password',
            ])
            ->call('updatePassword')
            ->assertHasErrors(['password']);

        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    });
});
