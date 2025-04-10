<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V0;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

it('allows an authenticated user to log out (revoke current token)', function (): void {
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

it('allows an authenticated user to log out from all devices (revoke all tokens)', function (): void {
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

it('prevents unauthenticated users from accessing logout endpoints', function (): void {
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
