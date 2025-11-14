<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

describe('Auth Abilities API', function (): void {
    it('returns token abilities for authenticated user', function (): void {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $abilities = ['read', 'create'];
        $token = $user->createToken('test-token', $abilities)->plainTextToken;

        $response = $this->withToken($token)->getJson(route('api.v0.auth.abilities'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'data' => $abilities,
            ]);
    });

    it('forbids access when token lacks the read ability', function (): void {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $token = $user->createToken('test-token', ['create'])->plainTextToken;

        $response = $this->withToken($token)->getJson(route('api.v0.auth.abilities'));

        $response->assertForbidden();
    });

    it('allows fetching the authenticated user when token includes the read ability', function (): void {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $token = $user->createToken('test-token', ['read'])->plainTextToken;

        $response = $this->withToken($token)->getJson(route('api.v0.auth.user'));

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id);
    });

    it('forbids fetching the authenticated user when token lacks the read ability', function (): void {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $token = $user->createToken('test-token', ['create'])->plainTextToken;

        $response = $this->withToken($token)->getJson(route('api.v0.auth.user'));

        $response->assertForbidden();
    });

    it('returns error for unauthenticated request', function (): void {
        $response = $this->getJson(route('api.v0.auth.abilities'));

        $response->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
            ]);
    });
});
