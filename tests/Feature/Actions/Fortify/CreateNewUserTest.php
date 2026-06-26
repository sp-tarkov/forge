<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Models\DisposableEmailBlocklist;
use App\Models\User;
use Illuminate\Validation\ValidationException;
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

    it('rejects registration with a disposable email address', function (): void {
        DisposableEmailBlocklist::query()->create(['domain' => 'tempmail.com']);

        $response = $this->followingRedirects()->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'test@tempmail.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'timezone' => 'America/New_York',
            'terms' => true,
        ]);

        $response->assertOk();
        $response->assertSee('This email address has been detected as disposable and is not supported.');
        $this->assertGuest();
    })->skip(fn (): bool => ! Features::enabled(Features::registration()), 'Registration support is not enabled.');

    it('translates a duplicate-email registration race into a validation error', function (): void {
        $input = [
            'name' => 'Racer One',
            'email' => 'race@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'timezone' => 'America/New_York',
            'terms' => true,
        ];

        // Simulate the race: a concurrent request claims the same email after our unique validation has passed but
        // before our insert runs. The creating hook fires once (guarded) so the conflicting factory insert that wins
        // the row does not recurse.
        $conflictInserted = false;
        User::creating(function (User $user) use (&$conflictInserted): void {
            if ($conflictInserted) {
                return;
            }

            $conflictInserted = true;
            User::factory()->create(['email' => $user->email]);
        });

        try {
            new CreateNewUser()->create($input);
            $this->fail('Expected a ValidationException for the duplicate email.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('email');
        }

        // Only the row that won the race exists; the losing registration did not create a duplicate.
        expect(User::query()->where('email', 'race@example.com')->count())->toBe(1);
    });
});
