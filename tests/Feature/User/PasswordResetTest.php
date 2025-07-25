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
});
