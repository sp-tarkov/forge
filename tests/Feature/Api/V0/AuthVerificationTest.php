<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

it('verifies a user with a valid signed url', function () {
    Event::fake();

    $user = User::factory()->create(['email_verified_at' => null]);
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify.api',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
    );
    $response = $this->getJson($verificationUrl);

    $response
        ->assertStatus(Response::HTTP_OK)
        ->assertExactJson([
            'success' => true,
            'data' => [
                'message' => 'Email verified successfully.',
            ],
        ]);

    $user->refresh();

    expect($user->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class, fn ($e) => $e->user->id === $user->id);
});

it('returns error if verification url signature is invalid', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $baseUrl = URL::route(
        'verification.verify.api',
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        absolute: false
    );
    $urlWithBadSignature = $baseUrl.'?expires=123&signature=badsignature';

    $response = $this->getJson($urlWithBadSignature);

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('returns error if verification url hash is invalid', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify.api',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => 'invalid-hash']
    );

    $response = $this->getJson($verificationUrl);

    $response
        ->assertStatus(Response::HTTP_BAD_REQUEST)
        ->assertExactJson([
            'success' => false,
            'code' => ApiErrorCode::VERIFICATION_INVALID->value,
            'message' => 'Invalid or expired verification link.',
        ]);
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('returns error if user is already verified', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify.api',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
    );

    $response = $this->getJson($verificationUrl);

    $response
        ->assertStatus(Response::HTTP_BAD_REQUEST)
        ->assertExactJson([
            'success' => false,
            'code' => ApiErrorCode::ALREADY_VERIFIED->value,
            'message' => 'Email already verified.',
        ]);
});

it('allows resend request for unverified user and sends notification', function () {
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

it('accepts resend request for verified user but sends no notification', function () {
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

it('accepts resend request for non-existent user but sends no notification', function () {
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

it('returns validation error if public resend email is missing', function () {
    $response = $this->postJson('/api/v0/auth/email/resend', [
        // email missing
    ]);

    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
        ->assertJsonValidationErrorFor('email');
});

it('returns validation error if public resend email is invalid', function () {
    $response = $this->postJson('/api/v0/auth/email/resend', [
        'email' => 'not-a-valid-email',
    ]);

    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
        ->assertJsonValidationErrorFor('email');
});

it('rate limits the public resend endpoint', function () {
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
