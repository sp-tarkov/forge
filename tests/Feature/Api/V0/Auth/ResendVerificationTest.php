<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

describe('Auth Resend Verification API', function (): void {
    it('allows resend request for unverified user and sends notification', function (): void {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->postJson('/api/v0/auth/email/resend', [
            'email' => $user->email,
        ]);

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertExactJson([
                'success' => true,
                'data' => [
                    'message' => 'If an account matching that email exists and requires verification, a new link has been sent.',
                ],
            ]);

        // Assert the notification was actually sent.
        Notification::assertSentTo($user, VerifyEmail::class);
    });

    it('accepts resend request for verified user but sends no notification', function (): void {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => now()]); // Already verified

        $response = $this->postJson('/api/v0/auth/email/resend', [
            'email' => $user->email,
        ]);

        // Response should still be generic success.
        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertExactJson([
                'success' => true,
                'data' => [
                    'message' => 'If an account matching that email exists and requires verification, a new link has been sent.',
                ],
            ]);

        // Assert no notification was sent.
        Notification::assertNothingSent();
    });

    it('accepts resend request for non-existent user but sends no notification', function (): void {
        Notification::fake();

        $nonExistentEmail = 'nobody@example.com';

        $response = $this->postJson('/api/v0/auth/email/resend', [
            'email' => $nonExistentEmail,
        ]);

        // Response should still be generic success.
        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertExactJson([
                'success' => true,
                'data' => [
                    'message' => 'If an account matching that email exists and requires verification, a new link has been sent.',
                ],
            ]);

        // Assert NO notification was sent.
        Notification::assertNothingSent();
    });

    it('returns validation error if public resend email is missing', function (): void {
        $response = $this->postJson('/api/v0/auth/email/resend', [
            // email missing
        ]);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
            ->assertJsonValidationErrorFor('email');
    });

    it('returns validation error if public resend email is invalid', function (): void {
        $response = $this->postJson('/api/v0/auth/email/resend', [
            'email' => 'not-a-valid-email',
        ]);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
            ->assertJsonValidationErrorFor('email');
    });

    it('rate limits the public resend endpoint', function (): void {
        $user = User::factory()->create(['email_verified_at' => null]);
        $email = $user->email;

        // Make 3 successful attempts
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/v0/auth/email/resend', ['email' => $email]);
            $response->assertStatus(Response::HTTP_OK);
        }

        // The 4th attempt should be throttled
        $response = $this->postJson('/api/v0/auth/email/resend', ['email' => $email]);
        $response->assertStatus(Response::HTTP_TOO_MANY_REQUESTS); // 429
    });
});
