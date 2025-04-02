<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V0\Auth\LoginRequest;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Authentication
 *
 * Endpoints for user authentication and token management.
 */
class AuthController extends Controller
{
    /**
     * User Login & Token Creation
     *
     * Authenticates a user with email and password and returns an API token. Users who registered via OAuth (and
     * haven't set a password) cannot use this endpoint.
     *
     * @response status=200 scenario="Successful Login"
     * {
     *     "success": true,
     *     "data": { "token": "{YOUR_API_TOKEN}" }
     * }
     * @response status=401 scenario="Invalid Credentials"
     * {
     *     "success": false,
     *     "code": "UNAUTHENTICATED",
     *     "message": "Invalid credentials provided."
     * }
     * @response status=403 scenario="OAuth User Attempt"
     * {
     *     "success": false,
     *     "code": "PASSWORD_LOGIN_UNAVAILABLE",
     *     "message": "Password login is not available for accounts created via OAuth. Please use the original login method or set a password for your account."
     * }
     * @response status=422 scenario="Validation Error"
     * {
     *     "success": false,
     *     "code": "VALIDATION_FAILED",
     *     "message": "Validation failed.",
     *     "errors": {
     *         "email": [ "The email field is required." ],
     *         "password": [ "The password field is required." ],
     *         "abilities.0": [ "The selected ability read-invalid is invalid. Allowed abilities are: create, read, update, delete" ]
     *     }
     * }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

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
     * User Logout (Current Token)
     *
     * Revokes the specific API token that was used to make this request.
     *
     * @authenticated
     *
     * @response status=200 scenario="Successful Logout"
     * {
     *     "success": true,
     *     "data": {
     *         "message": "Current token revoked successfully."
     *     }
     * }
     * @response status=401 scenario="Unauthenticated"
     * {
     *     "success": false,
     *     "code": "UNAUTHENTICATED",
     *     "message": "Unauthenticated."
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(['message' => 'Current token revoked successfully.']);
    }

    /**
     * User Logout (All Tokens)
     *
     * Revokes all API tokens associated with the authenticated user.
     *
     * @authenticated
     *
     * @response status=200 scenario="Successful Logout All"
     * {
     *     "success": true,
     *     "data": {
     *         "message": "All tokens revoked successfully."
     *     }
     * }
     * @response status=401 scenario="Unauthenticated"
     * {
     *     "success": false,
     *     "code": "UNAUTHENTICATED",
     *     "message": "Unauthenticated."
     * }
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return ApiResponse::success(['message' => 'All tokens revoked successfully.']);
    }
}
