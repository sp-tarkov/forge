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
});
