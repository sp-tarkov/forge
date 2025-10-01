<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

describe('email verification', function (): void {
    it('can render email verification screen', function (): void {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertStatus(200);
    })->skip(fn (): bool => ! Features::enabled(Features::emailVerification()), 'Email verification not enabled.');

    it('can verify email', function (): void {
        Event::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1((string) $user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('status', 'Your email address has been successfully verified.');
    })->skip(fn (): bool => ! Features::enabled(Features::emailVerification()), 'Email verification not enabled.');

    it('cannot verify email with invalid hash', function (): void {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    })->skip(fn (): bool => ! Features::enabled(Features::emailVerification()), 'Email verification not enabled.');

    it('throttles email verification requests', function (): void {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // Make 6 requests (the rate limit)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->actingAs($user)->post('/email/verification-notification');
            $response->assertSessionHasNoErrors();
        }

        // The 7th request should be throttled
        $response = $this->actingAs($user)->post('/email/verification-notification');
        $response->assertStatus(429);
        $response->assertSee('Too Many Requests');
    })->skip(fn (): bool => ! Features::enabled(Features::emailVerification()), 'Email verification not enabled.');

    it('displays custom 429 error page for throttled requests', function (): void {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // Quickly make requests to trigger rate limiting
        for ($i = 0; $i < 7; $i++) {
            $response = $this->actingAs($user)->post('/email/verification-notification');
        }

        // Last request should show custom error page
        $response->assertStatus(429);
        $response->assertSee('429');
        $response->assertSee('Too Many Requests');
        $response->assertSee('Please wait a moment before trying again');
    })->skip(fn (): bool => ! Features::enabled(Features::emailVerification()), 'Email verification not enabled.');

    it('can verify email when not authenticated', function (): void {
        Event::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1((string) $user->email)]
        );

        // Visit the verification URL without being authenticated
        $response = $this->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        // User should be verified
        expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

        // User should NOT be logged in after verification
        $this->assertGuest();

        // Should redirect to login with success message
        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHas('status', 'Your email address has been successfully verified. Please log in below to continue.');
    })->skip(fn (): bool => ! Features::enabled(Features::emailVerification()), 'Email verification not enabled.');

    it('does not log in user after unauthenticated email verification', function (): void {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1((string) $user->email)]
        );

        // Ensure we're not authenticated
        Auth::logout();
        $this->assertGuest();

        // Visit the verification URL
        $response = $this->get($verificationUrl);

        // Should still be guest after verification
        $this->assertGuest();

        // Should redirect to login page
        $response->assertRedirect(route('login', absolute: false));
    })->skip(fn (): bool => ! Features::enabled(Features::emailVerification()), 'Email verification not enabled.');

    it('does not re-verify already verified email when not authenticated', function (): void {
        Event::fake();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1((string) $user->email)]
        );

        // Visit the verification URL without being authenticated
        $response = $this->get($verificationUrl);

        // Verified event should not be dispatched
        Event::assertNotDispatched(Verified::class);

        // User should NOT be logged in
        $this->assertGuest();

        // Should redirect to login with already verified message
        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHas('status', 'Your email address has already been verified. Please log in to continue.');
    })->skip(fn (): bool => ! Features::enabled(Features::emailVerification()), 'Email verification not enabled.');

    it('redirects authenticated user with already verified email to dashboard', function (): void {
        Event::fake();

        $user = User::factory()->create([
            'email_verified_at' => now()->subDay(), // Already verified
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1((string) $user->email)]
        );

        // Visit the verification URL while authenticated
        $response = $this->actingAs($user)->get($verificationUrl);

        // Should not dispatch Verified event since already verified
        Event::assertNotDispatched(Verified::class);

        // Should redirect to dashboard with message
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('status', 'Your email address has already been verified.');
    })->skip(fn (): bool => ! Features::enabled(Features::emailVerification()), 'Email verification not enabled.');

    it('can render the email verification form properly', function (): void {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // Verify the form renders correctly with initial disabled state
        $response = $this->actingAs($user)->get('/email/verify');
        $response->assertStatus(200);
        $response->assertSee('Send Verification Email');
        // Should have initial delay to prevent abuse
        $response->assertSee('Please wait...');
    })->skip(fn (): bool => ! Features::enabled(Features::emailVerification()), 'Email verification not enabled.');
});
