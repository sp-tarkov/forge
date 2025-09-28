<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
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
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
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
