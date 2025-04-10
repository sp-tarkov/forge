<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V0;

use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

it('allows a new user to register', function (): void {
    $userData = [
        'name' => 'NewRegisterUser',
        'email' => 'register@example.com',
        'password' => 'Password123!',
    ];

    $response = $this->postJson('/api/v0/auth/register', $userData);

    $response
        ->assertStatus(Response::HTTP_CREATED) // 201
        ->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'profile_photo_url',
                'cover_photo_url',
                'created_at',
            ],
        ])
        ->assertJsonFragment(['success' => true])
        ->assertJsonPath('data.name', $userData['name']);

    // Assert user exists in database with correct details (excluding password check here)
    $this->assertDatabaseHas('users', [
        'name' => $userData['name'],
        'email' => $userData['email'],
    ]);

    // Assert password was hashed (by checking it's not the plain text one)
    $user = User::query()->where('email', $userData['email'])->first();
    expect(Hash::check($userData['password'], $user->password))->toBeTrue()
        ->and($user->password)->not->toEqual($userData['password']);
});

it('returns validation error if registration name is missing', function (): void {
    $response = $this->postJson('/api/v0/auth/register', [
        // name missing
        'email' => 'register@example.com',
        'password' => 'Password123!',
    ]);
    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('name');
});

it('returns validation error if registration email is invalid', function (): void {
    $response = $this->postJson('/api/v0/auth/register', [
        'name' => 'NewRegisterUser',
        'email' => 'not-an-email',
        'password' => 'Password123!',
    ]);
    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('email');
});

it('returns validation error if registration email is already taken', function (): void {
    $existingUser = User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/v0/auth/register', [
        'name' => 'NewRegisterUser',
        'email' => $existingUser->email, // Existing email
        'password' => 'Password123!',
    ]);
    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('email');
});

it('returns validation error if registration password is too short', function (): void {
    $response = $this->postJson('/api/v0/auth/register', [
        'name' => 'NewRegisterUser',
        'email' => 'register@example.com',
        'password' => 'short', // Minimum length is 8
    ]);
    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('password');
});

it('sends verification email upon registration', function (): void {
    Notification::fake();

    $userData = [
        'name' => 'Verify Me',
        'email' => 'verify@example.com',
        'password' => 'Password123!',
    ];

    $response = $this->postJson('/api/v0/auth/register', $userData);

    $response->assertStatus(Response::HTTP_CREATED);

    $user = User::query()->where('email', $userData['email'])->first();
    expect($user)->not->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});
