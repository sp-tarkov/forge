<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

test('reset password link screen can be rendered', function (): void {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
})->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password updates are not enabled.');

test('reset password link can be requested', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetPassword::class);
})->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password updates are not enabled.');

test('reset password screen can be rendered', function (): void {
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

test('password can be reset with valid token', function (): void {
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
