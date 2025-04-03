<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V0\Auth\ResendVerificationRequest;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Authentication
 *
 * Endpoints related to email verification.
 */
class VerificationController extends Controller
{
    /**
     * Mark the user's email address as verified.
     *
     * Handles the incoming request from the email verification link.
     *
     * @urlParam id integer required The ID of the user. Example: 1
     * @urlParam hash string required The verification hash for the user's email. Example: "a1b2c3d4..."
     *
     * @queryParam expires required The expiration timestamp for the signed URL. Example: 1678886400
     * @queryParam signature required The signature for the signed URL. Example: "a1b2c3d4..."
     *
     * @response status=200 scenario="Success"
     * {
     *     "success": true,
     *     "data": {
     *         "message": "Email verified successfully."
     *     }
     * }
     * @response status=400 scenario="Invalid/Expired URL"
     * {
     *     "success": false,
     *     "code": "VERIFICATION_INVALID_URL",
     *     "message": "Invalid or expired verification link."
     * }
     * @response status=400 scenario="Already Verified"
     * {
     *     "success": false,
     *     "code": "VERIFICATION_ALREADY_VERIFIED",
     *     "message": "Email already verified."
     * }
     */
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return ApiResponse::error(
                'Invalid or expired verification link.',
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VERIFICATION_INVALID
            );
        }

        $user = User::find($id);

        // Check if user exists and hash matches.
        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return ApiResponse::error(
                'Invalid or expired verification link.',
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VERIFICATION_INVALID,
            );
        }

        // Check if already verified.
        if ($user->hasVerifiedEmail()) {
            return ApiResponse::error(
                'Email already verified.',
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::ALREADY_VERIFIED,
            );
        }

        // Mark as verified and dispatch event
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return ApiResponse::success(['message' => 'Email verified successfully.']);
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
     * {
     *     "success": true,
     *     "data": {
     *         "message": "If an account matching that email exists and requires verification, a new link has been sent."
     *     }
     * }
     * @response status=422 scenario="Validation Error"
     * {
     *     "success": false,
     *     "code": "VALIDATION_FAILED",
     *     "message": "Validation failed.",
     *     "errors": {
     *         "email": [
     *             "The email field is required."
     *         ]
     *     }
     * }
     */
    public function resend(ResendVerificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        // Only send if user exists AND is not verified.
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        // Always return the same generic success message.
        return ApiResponse::success([
            'message' => 'If an account matching that email exists and requires verification, a new link has been sent.',
        ]);
    }
}
