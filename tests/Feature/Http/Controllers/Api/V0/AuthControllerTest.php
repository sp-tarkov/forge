<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiErrorCode;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\VerifyEmail;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

describe('login', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
    });

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
            ->assertJsonPath('data.token', fn ($token): bool => is_string($token) && mb_strlen($token) > 0);
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

    it('always adds the read ability when creating a token', function (): void {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v0/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'abilities' => ['update'],
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $token = PersonalAccessToken::query()->where('tokenable_id', $user->id)->first();
        expect($token)->not->toBeNull();
        expect($token->abilities)->toHaveCount(2)
            ->toContain('read')
            ->toContain('update');
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

    it('returns validation error if password exceeds maximum length', function (): void {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v0/auth/login', [
            'email' => 'test@example.com',
            'password' => str_repeat('a', 129),
        ]);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrorFor('password');
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
});

describe('logout', function (): void {
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
});

describe('register', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
    });

    it('allows a new user to register', function (): void {
        $userData = [
            'name' => 'NewRegisterUser',
            'email' => 'register@example.com',
            'password' => 'Password123!',
            'timezone' => 'America/New_York',
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
            'timezone' => $userData['timezone'],
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
            'timezone' => 'America/New_York',
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
            'timezone' => 'America/New_York',
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
            'timezone' => 'America/New_York',
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
            'timezone' => 'America/New_York',
        ]);
        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrorFor('password');
    });

    it('returns validation error if registration password exceeds maximum length', function (): void {
        $response = $this->postJson('/api/v0/auth/register', [
            'name' => 'NewRegisterUser',
            'email' => 'register@example.com',
            'password' => str_repeat('a', 129),
            'timezone' => 'America/New_York',
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
            'timezone' => 'America/New_York',
        ];

        $response = $this->postJson('/api/v0/auth/register', $userData);

        $response->assertStatus(Response::HTTP_CREATED);

        $user = User::query()->where('email', $userData['email'])->first();
        expect($user)->not->toBeNull();

        Notification::assertSentTo($user, VerifyEmail::class);
    });
});

describe('resendVerification', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
    });

    it('allows resend request for unverified user and sends notification', function (): void {
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

    it('accepts resend request for verified user but sends no notification', function (): void {
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

    it('accepts resend request for non-existent user but sends no notification', function (): void {
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

    it('returns validation error if public resend email is missing', function (): void {
        $response = $this->postJson('/api/v0/auth/email/resend', [
            // email missing
        ]);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
            ->assertJsonValidationErrorFor('email');
    });

    it('returns validation error if public resend email is invalid', function (): void {
        $response = $this->postJson('/api/v0/auth/email/resend', [
            'email' => 'not-a-valid-email',
        ]);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
            ->assertJsonValidationErrorFor('email');
    });

});

// Rate limiting keeps the throttle middleware enabled, so it is a top-level describe rather than nested inside
// resendVerification: nesting would inherit that group's withoutMiddleware() beforeEach and disable the throttle. The
// limiter is reset with Cache::flush() before the run.
describe('resendVerification rate limiting', function (): void {
    beforeEach(function (): void {
        Cache::flush();
    });

    it('rate limits the public resend endpoint', function (): void {
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
});

describe('user', function (): void {
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
                    'updated_at',
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
            'email_verified_at' => now(),
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
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.role.id', $role->id)
            ->assertJsonPath('data.role.name', $role->name);
    });

    it('throws error on invalid include parameters', function (): void {
        $role = UserRole::factory()->create(['name' => 'Tester']);
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'user_role_id' => $role->id,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Request includes 'role' (valid) and 'invalid_relation' (invalid)
        $response = $this->withToken($token)->getJson('/api/v0/auth/user?include=role,invalid_relation');

        $response
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonStructure([
                'success',
                'code',
                'message',
            ])
            ->assertJsonPath('success', false);
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
                    'updated_at',
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
});

describe('abilities', function (): void {
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
