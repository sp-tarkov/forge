<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

describe('registration', function (): void {
    it('can render registration screen', function (): void {
        $response = $this->get('/register');

        $response->assertStatus(200);
    })->skip(fn (): bool => ! Features::enabled(Features::registration()), 'Registration support is not enabled.');

    it('allows new users to register', function (): void {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'timezone' => 'America/New_York',
            'terms' => true,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    })->skip(fn (): bool => ! Features::enabled(Features::registration()), 'Registration support is not enabled.');

    it('rejects passwords exceeding maximum length', function (): void {
        $longPassword = str_repeat('a', 129);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => $longPassword,
            'password_confirmation' => $longPassword,
            'timezone' => 'America/New_York',
            'terms' => true,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    })->skip(fn (): bool => ! Features::enabled(Features::registration()), 'Registration support is not enabled.');

    it('renders validation error messages on the registration form', function (): void {
        $response = $this->followingRedirects()->from('/register')->post('/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
            'timezone' => 'America/New_York',
            'terms' => false,
        ]);

        $response->assertOk();
        $response->assertSee('The name field is required.');
        $response->assertSee('The email field must be a valid email address.');
        $response->assertSee('The terms field must be accepted.');
        $this->assertGuest();
    })->skip(fn (): bool => ! Features::enabled(Features::registration()), 'Registration support is not enabled.');
});
