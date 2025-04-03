<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

it('retrieves basic user details for authenticated user', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]);
    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v0/auth/user');

    $response
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'email', // Included for self
                'email_verified_at', // Included for self
                'profile_photo_url',
                'cover_photo_url',
                'created_at',
                // 'role' should NOT be here by default
            ],
        ])
        ->assertJsonFragment(['success' => true])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', $user->name)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.role');
});

it('includes role when requested via include parameter', function (): void {
    $role = UserRole::factory()->create(['name' => 'Tester']);
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
        'user_role_id' => $role->id, // Assign role
    ]);
    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v0/auth/user?include=role');

    $response
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'profile_photo_url',
                'cover_photo_url',
                'role' => [
                    'id',
                    'name',
                    'short_name',
                    'description',
                    'color_class',
                ],
                'created_at',
            ],
        ])
        ->assertJsonPath('data.role.id', $role->id)
        ->assertJsonPath('data.role.name', $role->name);
});

it('ignores invalid include parameters', function (): void {
    $role = UserRole::factory()->create(['name' => 'Tester']);
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
        'user_role_id' => $role->id,
    ]);
    $token = $user->createToken('test-token')->plainTextToken;

    // Request includes 'role' (valid) and 'invalid_relation' (invalid)
    $response = $this->withToken($token)->getJson('/api/v0/auth/user?include=role,invalid_relation');

    $response
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'profile_photo_url',
                'cover_photo_url',
                'role' => [
                    'id',
                    'name',
                    'short_name',
                    'description',
                    'color_class',
                ],
                'created_at',
            ],
        ])
        ->assertJsonMissingPath('data.invalid_relation'); // Should not appear
});

it('should handle a null role gracefully when the role is requested', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
        'user_role_id' => null,
    ]);
    $token = $user->createToken('test-token')->plainTextToken;

    // Request includes 'role'
    $response = $this->withToken($token)->getJson('/api/v0/auth/user?include=role');

    $response
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'profile_photo_url',
                'cover_photo_url',
                'role',
                'created_at',
            ],
        ])
        ->assertJsonPath('data.role', null)
        ->assertJsonMissingPath('data.role.id');
});

it('prevents unauthenticated users from accessing the user endpoint', function (): void {
    $response = $this->getJson('/api/v0/auth/user');
    $response
        ->assertStatus(Response::HTTP_UNAUTHORIZED)
        ->assertExactJson([
            'success' => false,
            'code' => ApiErrorCode::UNAUTHENTICATED->value,
            'message' => 'Unauthenticated.',
        ]);
});
