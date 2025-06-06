<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V0\Auth\LoginRequest;
use App\Http\Requests\Api\V0\Auth\RegisterRequest;
use App\Http\Requests\Api\V0\Auth\ResendVerificationRequest;
use App\Http\Resources\Api\V0\UserResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Authentication
 *
 * Endpoints for user authentication and token management.
 */
class AuthController extends Controller
{
    /**
     * Login & Token Creation
     *
     * Authenticates a user with email and password and returns an API token. Users who registered via OAuth (and
     * haven't set a password) cannot use this endpoint.
     *
     * @unauthenticated
     *
     * @response status=200 scenario="Successful Login"
     *  {
     *      "success": true,
     *      "data": {
     *          "token": "{YOUR_API_TOKEN}"
     *      }
     *  }
     * @response status=401 scenario="Invalid Credentials"
     *  {
     *      "success": false,
     *      "code": "UNAUTHENTICATED",
     *      "message": "Invalid credentials provided."
     *  }
     * @response status=403 scenario="OAuth User Attempt"
     *  {
     *      "success": false,
     *      "code": "PASSWORD_LOGIN_UNAVAILABLE",
     *      "message": "Password login is not available for accounts created via OAuth. Please use the original login method or set a password for your account."
     *  }
     * @response status=422 scenario="Validation Error"
     *  {
     *      "success": false,
     *      "code": "VALIDATION_FAILED",
     *      "message": "Validation failed.",
     *      "errors": {
     *          "email": [ "The email field is required." ],
     *          "password": [ "The password field is required." ],
     *          "abilities.0": [ "The selected ability read-invalid is invalid. Allowed abilities are: create, read, update, delete" ]
     *      }
     *  }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || is_null($user->password)) {

            // If password is null, it's likely an OAuth user.
            if ($user && is_null($user->password)) {
                return ApiResponse::error(
                    'Password login is not available for accounts created via OAuth. Please use the original login method or set a password for your account.',
                    Response::HTTP_FORBIDDEN,
                    ApiErrorCode::PASSWORD_LOGIN_UNAVAILABLE,
                );
            }

            // User doesn't exist.
            return ApiResponse::error(
                'Invalid credentials provided.',
                Response::HTTP_UNAUTHORIZED,
                ApiErrorCode::INVALID_CREDENTIALS
            );
        }

        // Does the provided password match the stored hash?
        if (! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error(
                'Invalid credentials provided.',
                Response::HTTP_UNAUTHORIZED,
                ApiErrorCode::INVALID_CREDENTIALS
            );
        }

        // Authentication Successful

        // Determine token name
        $tokenName = $validated['token_name'] ?? 'auth_token_'.Str::random(5);

        // Determine and filter abilities
        $allowedAbilities = ['create', 'read', 'update', 'delete'];
        $requestedAbilities = $validated['abilities'] ?? ['read'];
        $validAbilities = array_intersect($requestedAbilities, $allowedAbilities);
        $abilitiesToGrant = ! empty($validAbilities) ? $validAbilities : ['read'];

        // Create the token
        $token = $user->createToken($tokenName, $abilitiesToGrant)->plainTextToken;

        return ApiResponse::success(['token' => $token]);
    }

    /**
     * Logout & Delete Current Token
     *
     * Revokes the specific API token that was used to make this request.
     *
     * @authenticated
     *
     * @response status=200 scenario="Successful Logout"
     *  {
     *      "success": true,
     *      "data": {
     *          "message": "Current token revoked successfully."
     *      }
     *  }
     * @response status=401 scenario="Unauthenticated"
     *  {
     *      "success": false,
     *      "code": "UNAUTHENTICATED",
     *      "message": "Unauthenticated."
     *  }
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(['message' => 'Current token revoked successfully.']);
    }

    /**
     * Logout & Delete All Tokens
     *
     * Revokes all API tokens associated with the authenticated user.
     *
     * @authenticated
     *
     * @response status=200 scenario="Successful Logout All"
     *  {
     *      "success": true,
     *      "data": {
     *          "message": "All tokens revoked successfully."
     *      }
     *  }
     * @response status=401 scenario="Unauthenticated"
     *  {
     *      "success": false,
     *      "code": "UNAUTHENTICATED",
     *      "message": "Unauthenticated."
     *  }
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return ApiResponse::success(['message' => 'All tokens revoked successfully.']);
    }

    /**
     * Get Authenticated User
     *
     * Retrieves the details for the currently authenticated user based on the provided API token.
     *
     * @authenticated
     *
     * @queryParam include string optional Comma-separated list of relationships to include. Available relationships: `role`. Example: role
     *
     * @response status=200 scenario="Success (No Includes)"
     *  {
     *      "success": true,
     *      "data": {
     *      "id": 1,
     *          "name": "Test User",
     *          "email": "test@example.com",
     *          "email_verified_at": "2025-04-02T20:44:38.000000Z",
     *          "profile_photo_url": "https://example.com/path/to/profile.jpg",
     *          "cover_photo_url": "https://example.com/path/to/cover.jpg",
     *          "created_at": "2025-04-01T10:00:00.000000Z"
     *      }
     *  }
     * @response status=200 scenario="Success (Include Role)"
     *  {
     *      "success": true,
     *      "data": {
     *          "id": 1,
     *          "name": "Test User",
     *          "email": "test@example.com",
     *          "email_verified_at": "2025-04-02T20:44:38.000000Z",
     *          "profile_photo_url": "https://example.com/path/to/profile.jpg",
     *          "cover_photo_url": "https://example.com/path/to/cover.jpg",
     *          "role": {
     *              "id": 2,
     *              "name": "Moderator",
     *              "short_name": "Mod",
     *              "description": "Moderate user content.",
     *              "color_class": "emerald",
     *          },
     *          "created_at": "2025-04-01T10:00:00.000000Z",
     *          "updated_at": "2025-04-01T10:00:00.000000Z"
     *      }
     *  }
     * @response status=401 scenario="Unauthenticated"
     *  {
     *      "success": false,
     *      "code": "UNAUTHENTICATED",
     *      "message": "Unauthenticated."
     *  }
     */
    public function user(Request $request): JsonResponse
    {
        $user = QueryBuilder::for(User::query()->where('id', $request->user()->id))
            ->allowedIncludes([
                AllowedInclude::relationship('role'),
                // TODO:
                // AllowedInclude::relationship('ownedMods'),
                // AllowedInclude::relationship('authoredMods'),
                // AllowedInclude::relationship('followers'),
                // AllowedInclude::relationship('following'),
            ])
            ->firstOrFail();

        return ApiResponse::success(new UserResource($user));
    }

    /**
     * Registration
     *
     * Creates a new user account. Email verification is still required.
     *
     * @unauthenticated
     *
     * @response status=201 scenario="Successful Registration"
     *  {
     *      "success": true,
     *      "data": {
     *          "id": 2,
     *          "name": "NewUser123",
     *          "profile_photo_url": "https://ui-avatars.com/api/?name=NewUser123&color=...",
     *          "cover_photo_url": "https://picsum.photos/seed/NewUser123/...",
     *          "timezone": "America/New_York",
     *          "created_at": "2025-04-02T21:30:00.000000Z",
     *          "updated_at": "2025-04-01T10:00:00.000000Z"
     *      }
     *  }
     * @response status=422 scenario="Validation Error"
     *  {
     *      "success": false,
     *      "code": "VALIDATION_FAILED",
     *      "message": "Validation failed.",
     *      "errors": {
     *          "name": [
     *              "The name has already been taken."
     *          ],
     *          "email": [
     *              "The email must be a valid email address."
     *          ],
     *          "password": [
     *              "The password must be at least 8 characters."
     *          ],
     *          "timezone": [
     *              "The timezone field is required."
     *          ]
     *      }
     *  }
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'timezone' => $validated['timezone'],
        ]);

        $user->sendEmailVerificationNotification();

        return ApiResponse::success(new UserResource($user), Response::HTTP_CREATED);
    }

    /**
     * Resend Email Verification
     *
     * Allows anyone to request a verification email resend by providing an email address. Use this if a user registered
     * but did not receive the initial email and cannot log in. For security, this endpoint always returns a success
     * message, regardless of whether the email exists or is already verified, to prevent email enumeration.
     *
     * This endpoint is heavily rate-limited.
     *
     * @unauthenticated
     *
     * @response status=200 scenario="Request Accepted"
     *  {
     *      "success": true,
     *      "data": {
     *          "message": "If an account matching that email exists and requires verification, a new link has been sent."
     *      }
     *  }
     * @response status=422 scenario="Validation Error"
     *  {
     *      "success": false,
     *      "code": "VALIDATION_FAILED",
     *      "message": "Validation failed.",
     *      "errors": {
     *          "email": [
     *              "The email field is required."
     *          ]
     *      }
     *  }
     */
    public function resend(ResendVerificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        // Only send if user exists AND is not verified.
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        // Always return the same generic success message.
        return ApiResponse::success([
            'message' => 'If an account matching that email exists and requires verification, a new link has been sent.',
        ]);
    }

    /**
     * Token Abilities
     *
     * Get the current token's abilities.
     *
     * @authenticated
     *
     * @response status=200 scenario="Success"
     * {
     *     "success": true,
     *     "data": [
     *         "read",
     *         "create",
     *         "update",
     *         "delete"
     *     ]
     * }
     * @response status=401 scenario="Unauthenticated"
     * {
     *     "success": false,
     *     "code": "UNAUTHENTICATED",
     *     "message": "Unauthenticated."
     * }
     */
    public function abilities(Request $request): JsonResponse
    {
        $token = PersonalAccessToken::findToken($request->bearerToken());

        if (! $token) {
            // This should never happen, as the middleware should ensure a valid token is present. Just in case...
            return ApiResponse::error(
                'Invalid token provided.',
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::UNEXPECTED_ERROR
            );
        }

        $abilities = $token->abilities;

        // Ensure empty abilities are returned as an empty array
        if (empty($abilities)) {
            $abilities = [];
        }

        return ApiResponse::success($abilities);
    }
}
