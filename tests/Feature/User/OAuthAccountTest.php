<?php

declare(strict_types=1);

use App\Livewire\Profile\UpdatePasswordForm;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

it('creates a new user and attaches the OAuth provider when logging in via OAuth', function (): void {
    // Mock the Socialite user
    $mock = Mockery::mock(SocialiteUser::class);
    $mock->shouldReceive('getId')->andReturn('provider-user-id');
    $mock->shouldReceive('getEmail')->andReturn('newuser@example.com');
    $mock->shouldReceive('getName')->andReturn('New User');
    $mock->shouldReceive('getNickname')->andReturn(null);
    $mock->shouldReceive('getAvatar')->andReturn('avatar-url');
    $mock->token = 'access-token';
    $mock->refreshToken = 'refresh-token';

    // Mock Socialite facade
    Socialite::shouldReceive('driver->user')->andReturn($mock);

    // Hit the callback route
    $response = $this->get('/login/discord/callback');

    // Assert that the user was created
    $user = User::query()->where('email', 'newuser@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New User');

    // Assert that the OAuth provider was attached
    $oAuthConnection = $user->oAuthConnections()->whereProvider('discord')->first();
    expect($oAuthConnection)->not->toBeNull()
        ->and($oAuthConnection->provider_id)->toBe('provider-user-id');

    // Assert the user is authenticated
    $this->assertAuthenticatedAs($user);

    // Assert redirect to dashboard
    $response->assertRedirect(route('dashboard'));
});

it('attaches a new OAuth provider to an existing user when logging in via OAuth', function (): void {
    // Create an existing user
    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
        'name' => 'Existing User',
        'password' => Hash::make('password123'),
    ]);

    // Mock the Socialite user
    $mock = Mockery::mock(SocialiteUser::class);
    $mock->shouldReceive('getId')->andReturn('new-provider-user-id');
    $mock->shouldReceive('getEmail')->andReturn('existinguser@example.com');
    $mock->shouldReceive('getName')->andReturn('Existing User Updated');
    $mock->shouldReceive('getNickname')->andReturn(null);
    $mock->shouldReceive('getAvatar')->andReturn('new-avatar-url');
    $mock->token = 'new-access-token';
    $mock->refreshToken = 'new-refresh-token';

    // Mock Socialite facade
    Socialite::shouldReceive('driver->user')->andReturn($mock);

    // Hit the callback route
    $response = $this->get('/login/discord/callback');

    // Refresh user data
    $user->refresh();

    // Assert that the username was not updated
    expect($user->name)->toBe('Existing User')
        ->and($user->name)->not->toBe('Existing User Updated');

    // Assert that the new OAuth provider was attached
    $oauthConnection = $user->oAuthConnections()->whereProvider('discord')->first();
    expect($oauthConnection)->not->toBeNull()
        ->and($oauthConnection->provider_id)->toBe('new-provider-user-id');

    // Assert the user is authenticated
    $this->assertAuthenticatedAs($user);

    // Assert redirect to dashboard
    $response->assertRedirect(route('dashboard'));
});

it('hides the current password field when the user has no password', function (): void {
    // Create a user with no password
    $user = User::factory()->create([
        'password' => null,
    ]);

    $this->actingAs($user);

    // Visit the profile page
    $response = $this->get('/user/profile');
    $response->assertStatus(200);

    // Assert that the current password field is not displayed
    $response->assertDontSee(__('Current Password'));
});

it('shows the current password field when the user has a password', function (): void {
    // Create a user with a password
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $this->actingAs($user);

    // Visit the profile page
    $response = $this->get('/user/profile');
    $response->assertStatus(200);

    // Assert that the current password field is displayed
    $response->assertSee(__('Current Password'));
});

it('allows a user without a password to set a new password without entering the current password', function (): void {
    // Create a user with a NULL password
    $user = User::factory()->create([
        'password' => null,
    ]);

    $this->actingAs($user);

    // Test the Livewire component
    Livewire::test(UpdatePasswordForm::class)
        ->set('state.password', 'newpassword123')
        ->set('state.password_confirmation', 'newpassword123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    // Refresh user data
    $user->refresh();

    // Assert that the password is now set
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});

it('requires a user with a password to enter the current password to set a new password', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('oldpassword'),
    ]);

    $this->actingAs($user);

    // Without current password
    Livewire::test(UpdatePasswordForm::class)
        ->set('state.password', 'newpassword123')
        ->set('state.password_confirmation', 'newpassword123')
        ->call('updatePassword')
        ->assertHasErrors(['current_password' => 'required']);

    // With incorrect current password
    Livewire::test(UpdatePasswordForm::class)
        ->set('state.current_password', 'wrongpassword')
        ->set('state.password', 'newpassword123')
        ->set('state.password_confirmation', 'newpassword123')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);

    // With correct current password
    Livewire::test(UpdatePasswordForm::class)
        ->set('state.current_password', 'oldpassword')
        ->set('state.password', 'newpassword123')
        ->set('state.password_confirmation', 'newpassword123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    $user->refresh();

    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});
