<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

describe('password reset', function (): void {
    it('can render reset password link screen', function (): void {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    })->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password updates are not enabled.');

    it('can request reset password link', function (): void {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
    })->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password updates are not enabled.');

    it('can render reset password screen', function (): void {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification): true {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    })->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password updates are not enabled.');

    it('can reset password with valid token', function (): void {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user): true {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertSessionHasNoErrors();

            return true;
        });
    })->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password updates are not enabled.');

    it('shows same message for valid and invalid email addresses', function (): void {
        Notification::fake();

        $user = User::factory()->create();

        // Request password reset for a valid email
        $validResponse = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        // Request password reset for an invalid email
        $invalidResponse = $this->post('/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        // Both should redirect back
        $validResponse->assertRedirect();
        $invalidResponse->assertRedirect();

        // Get the session messages
        $validMessage = $validResponse->getSession()->get('status');
        $invalidMessage = $invalidResponse->getSession()->get('status');

        // Expected ambiguous message
        $expectedMessage = 'If your email address is in our system, we have sent you a password reset link.';

        // Both should have a status message (not an error)
        expect($validMessage)->not->toBeNull();
        expect($invalidMessage)->not->toBeNull();

        // Both messages should be identical
        expect($validMessage)->toBe($invalidMessage);

        // Both messages should be the expected ambiguous message
        expect($validMessage)->toBe($expectedMessage);
        expect($invalidMessage)->toBe($expectedMessage);

        // Should not have validation errors
        $validResponse->assertSessionHasNoErrors();
        $invalidResponse->assertSessionHasNoErrors();

        // Notification should only be sent to the valid user
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertNothingSentTo(new User(['email' => 'nonexistent@example.com']));
    })->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password updates are not enabled.');
});
