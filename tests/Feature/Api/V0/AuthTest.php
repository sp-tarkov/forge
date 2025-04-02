<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

it('allows a user with correct credentials to log in and receive a token', function () {
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
        ->assertJsonPath('data.token', fn ($token) => is_string($token) && strlen($token) > 0);
});

it('creates a token with a custom name when provided', function () {
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

it('creates a token with specified valid abilities', function () {
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

it('creates a token with default abilities when abilities array is empty or omitted', function () {
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

it('returns error for incorrect password', function () {
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

it('returns error for non-existent email', function () {
    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'nouser@example.com',
        'password' => 'password123',
    ]);

    // Note: Because validation runs first, 'email.exists' rule catches this
    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422 due to validation rule
        ->assertJsonValidationErrorFor('email');
});

it('returns error for oauth user trying password login', function () {
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

it('returns validation error if email is missing', function () {
    $response = $this->postJson('/api/v0/auth/login', [
        // 'email' => 'test@example.com', // Missing
        'password' => 'password123',
    ]);

    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
        ->assertJsonValidationErrorFor('email');
});

it('returns validation error if password is missing', function () {
    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'test@example.com',
        // 'password' => 'password123', // Missing
    ]);

    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
        ->assertJsonValidationErrorFor('password');
});

it('returns validation error if email format is invalid', function () {
    $response = $this->postJson('/api/v0/auth/login', [
        'email' => 'not-an-email',
        'password' => 'password123',
    ]);

    $response
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
        ->assertJsonValidationErrorFor('email');
});

it('returns validation error if abilities contains invalid value', function () {
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

it('returns validation error if abilities is not an array', function () {
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

it('allows an authenticated user to log out (revoke current token)', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);
    $tokenValue = $user->createToken('test-token')->plainTextToken;
    $tokenId = PersonalAccessToken::findToken(explode('|', $tokenValue, 2)[1])->id;

    // Ensure token exists before logout
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);

    $response = $this->withToken($tokenValue)->postJson('/api/v0/auth/logout');

    $response
        ->assertStatus(Response::HTTP_OK)
        ->assertExactJson([
            'success' => true,
            'data' => [
                'message' => 'Current token revoked successfully.',
            ],
        ]);

    // Ensure the specific token used is now deleted
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
});

it('allows an authenticated user to log out from all devices (revoke all tokens)', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);

    // Create multiple tokens
    $tokenValue1 = $user->createToken('token1')->plainTextToken;
    $user->createToken('token2');
    $user->createToken('token3');

    // Ensure tokens exist
    expect($user->tokens()->count())->toBe(3);

    $response = $this->withToken($tokenValue1)->postJson('/api/v0/auth/logout/all');

    $response
        ->assertStatus(Response::HTTP_OK)
        ->assertExactJson([
            'success' => true,
            'data' => [
                'message' => 'All tokens revoked successfully.',
            ],
        ]);

    // Ensure all tokens for this user are deleted
    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    expect($user->refresh()->tokens()->count())->toBe(0); // Another check
});

it('prevents unauthenticated users from accessing logout endpoints', function () {
    // No token provided
    $responseLogout = $this->postJson('/api/v0/auth/logout');
    $responseLogout
        ->assertStatus(Response::HTTP_UNAUTHORIZED) // Should fail auth middleware
        ->assertJsonFragment(['message' => 'Unauthenticated.']);

    $responseLogoutAll = $this->postJson('/api/v0/auth/logout/all');
    $responseLogoutAll
        ->assertStatus(Response::HTTP_UNAUTHORIZED) // Should fail auth middleware
        ->assertJsonFragment(['message' => 'Unauthenticated.']);
});
