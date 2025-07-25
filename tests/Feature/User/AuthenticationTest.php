<?php

declare(strict_types=1);

use App\Models\User;

describe('login page', function (): void {
    it('can be rendered', function (): void {
        $response = $this->get('/login');

        $response->assertStatus(200);
    });
});

describe('authentication', function (): void {
    it('authenticates users with valid credentials', function (): void {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    });

    it('does not authenticate users with invalid password', function (): void {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    });

    it('does not authenticate users with null password', function (): void {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => null,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('password');
    });
});

describe('social authentication', function (): void {
    it('redirects to Discord for authentication', function (): void {
        $response = $this->get(route('login.socialite', ['provider' => 'discord']));

        $response->assertStatus(302);
        $response->assertRedirect();
    });
});
