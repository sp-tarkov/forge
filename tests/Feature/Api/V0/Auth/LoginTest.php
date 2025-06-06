<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

it('allows a user with correct credentials to log in and receive a token', function (): void {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonStructure([
            'success',
            'data' => [
                'token',
            ],
        ])
        ->assertJsonFragment(['success' => true])
        ->assertJsonPath('data.token', fn ($token): bool => is_string($token) && strlen($token) > 0);
});

it('creates a token with a custom name when provided', function (): void {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);
    $customTokenName = 'my-special-token';

    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
        'token_name' => $customTokenName,
    ]);

    $response->assertStatus(Response::HTTP_OK);

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
        'name' => $customTokenName,
    ]);
});

it('creates a token with specified valid abilities', function (): void {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);
    $abilities = ['read', 'update'];

    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
        'abilities' => $abilities,
    ]);

    $response->assertStatus(Response::HTTP_OK);

    // Find the created token and check its abilities
    $token = PersonalAccessToken::query()->where('tokenable_id', $user->id)->first();
    expect($token)->not->toBeNull()
        ->and($token->abilities)->toEqual($abilities);
});

it('creates a token with default abilities when abilities array is empty or omitted', function (): void {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);
    $defaultAbilities = ['read'];

    // Test omitted abilities parameter
    $responseOmitted = $this->postJson('/api/v0/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $responseOmitted->assertStatus(Response::HTTP_OK);
    $tokenOmitted = PersonalAccessToken::query()->where('tokenable_id', $user->id)->first();
    expect($tokenOmitted->abilities)->toEqual($defaultAbilities);

    $tokenOmitted->delete(); // Clean up

    // Test empty array
    $responseEmpty = $this->postJson('/api/v0/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
        'abilities' => [],
    ]);

    $responseEmpty->assertStatus(Response::HTTP_OK);
    $tokenEmpty = PersonalAccessToken::query()->where('tokenable_id', $user->id)->first();
    expect($tokenEmpty->abilities)->toEqual($defaultAbilities);
});

it('returns error for incorrect password', function (): void {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertStatus(Response::HTTP_UNAUTHORIZED) // 401
        ->assertExactJson([
            'success' => false,
            'code' => ApiErrorCode::INVALID_CREDENTIALS->value,
            'message' => 'Invalid credentials provided.',
        ]);
});

it('returns error for non-existent email', function (): void {
    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'nouser@example.com',
        'password' => 'password123',
    ]);

    // Note: Because validation runs first, 'email.exists' rule catches this
    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422 due to validation rule
        ->assertJsonValidationErrorFor('email');
});

it('returns error for oauth user trying password login', function (): void {
    // Create user with NULL password
    User::factory()->create([
        'email' => 'oauth@example.com',
        'password' => null,
    ]);

    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'oauth@example.com',
        'password' => 'any-password', // Doesn't matter
    ]);

    $response
        ->assertStatus(Response::HTTP_FORBIDDEN) // 403
        ->assertExactJson([
            'success' => false,
            'code' => ApiErrorCode::PASSWORD_LOGIN_UNAVAILABLE->value,
            'message' => 'Password login is not available for accounts created via OAuth. Please use the original login method or set a password for your account.',
        ]);
});

it('returns validation error if email is missing', function (): void {
    $response = $this->postJson('/api/v0/auth/login', [
        // 'email' => 'test@example.com', // Missing
        'password' => 'password123',
    ]);

    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
        ->assertJsonValidationErrorFor('email');
});

it('returns validation error if password is missing', function (): void {
    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'test@example.com',
        // 'password' => 'password123', // Missing
    ]);

    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
        ->assertJsonValidationErrorFor('password');
});

it('returns validation error if email format is invalid', function (): void {
    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'not-an-email',
        'password' => 'password123',
    ]);

    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
        ->assertJsonValidationErrorFor('email');
});

it('returns validation error if abilities contains invalid value', function (): void {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
        'abilities' => ['read', 'invalid-ability'], // Contains bad value
    ]);

    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
        ->assertJsonValidationErrorFor('abilities.1'); // Validation rule targets the specific invalid item
});

it('returns validation error if abilities is not an array', function (): void {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
        'abilities' => 'read', // Invalid type
    ]);

    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
        ->assertJsonValidationErrorFor('abilities');
});
