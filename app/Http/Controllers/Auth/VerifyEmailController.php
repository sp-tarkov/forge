<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyEmailRequest;
use App\Http\Responses\VerifyEmailResponse;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

class VerifyEmailController extends Controller
{
    /**
     * Mark the user's email address as verified.
     *
     * @return VerifyEmailResponse
     */
    public function __invoke(VerifyEmailRequest $request)
    {
        // Get user from route parameters since they might not be authenticated
        $user = User::query()->findOrFail($request->route('id'));

        // If already verified, return response indicating it's not newly verified
        if ($user->hasVerifiedEmail()) {
            return new VerifyEmailResponse(false);
        }

        // Mark as verified
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return new VerifyEmailResponse(true);
    }
}
