<?php

declare(strict_types=1);

use App\Models\User;

test('login screen can be rendered', function (): void {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function (): void {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users cannot authenticate with invalid password', function (): void {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can authenticate using Discord', function (): void {
    $response = $this->get(route('login.socialite', ['provider' => 'discord']));

    $response->assertStatus(302);
    $response->assertRedirect();
});

test('user can not authenticate using a null password', function (): void {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => null,
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('password');
});
