<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

describe('password update', function (): void {
    it('can update password', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test('profile.update-password-form')
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

        Livewire::test('profile.update-password-form')
            ->set('state', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);

        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    });

    it('rejects passwords exceeding maximum length', function (): void {
        $this->actingAs($user = User::factory()->create());
        $longPassword = str_repeat('a', 129);

        Livewire::test('profile.update-password-form')
            ->set('state', [
                'current_password' => 'password',
                'password' => $longPassword,
                'password_confirmation' => $longPassword,
            ])
            ->call('updatePassword')
            ->assertHasErrors(['password']);

        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    });

    it('requires new passwords to match', function (): void {
        $this->actingAs($user = User::factory()->create());

        Livewire::test('profile.update-password-form')
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

describe('current password field visibility', function (): void {
    it('hides the current password field when the user has no password', function (): void {
        $user = User::factory()->create([
            'password' => null,
        ]);

        $this->actingAs($user);

        $response = $this->get('/user/profile');
        $response->assertStatus(200);

        $response->assertDontSee(__('Current Password'));
    });

    it('shows the current password field when the user has a password', function (): void {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $this->actingAs($user);

        $response = $this->get('/user/profile');
        $response->assertStatus(200);

        $response->assertSee(__('Current Password'));
    });
});

describe('password update for OAuth users without a password', function (): void {
    it('allows a user without a password to set a new password without entering the current password', function (): void {
        // Create a user with a NULL password.
        $user = User::factory()->create([
            'password' => null,
        ]);

        $this->actingAs($user);

        Livewire::test('profile.update-password-form')
            ->set('state.password', 'newpassword123')
            ->set('state.password_confirmation', 'newpassword123')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $user->refresh();

        expect(Hash::check('newpassword123', $user->password))->toBeTrue();
    });

    it('requires a user with a password to enter the current password to set a new password', function (): void {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        $this->actingAs($user);

        // Without current password.
        Livewire::test('profile.update-password-form')
            ->set('state.password', 'newpassword123')
            ->set('state.password_confirmation', 'newpassword123')
            ->call('updatePassword')
            ->assertHasErrors(['current_password' => 'required']);

        // With incorrect current password.
        Livewire::test('profile.update-password-form')
            ->set('state.current_password', 'wrongpassword')
            ->set('state.password', 'newpassword123')
            ->set('state.password_confirmation', 'newpassword123')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);

        // With correct current password.
        Livewire::test('profile.update-password-form')
            ->set('state.current_password', 'oldpassword')
            ->set('state.password', 'newpassword123')
            ->set('state.password_confirmation', 'newpassword123')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $user->refresh();

        expect(Hash::check('newpassword123', $user->password))->toBeTrue();
    });
});
