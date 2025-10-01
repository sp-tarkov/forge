<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function __construct(protected bool $justVerified = true) {}

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        // For API requests
        if ($request->wantsJson()) {
            return new JsonResponse(['verified' => true], 200);
        }

        // If user is not authenticated, redirect to login with success message
        if (! $request->user()) {
            $message = $this->justVerified
                ? 'Your email address has been successfully verified. Please log in below to continue.'
                : 'Your email address has already been verified. Please log in to continue.';

            return redirect()->route('login')->with('status', $message);
        }

        // If authenticated, always redirect to dashboard with success message
        $dashboardUrl = route('dashboard');

        if ($this->justVerified) {
            return redirect($dashboardUrl)->with('status', 'Your email address has been successfully verified.');
        }

        return redirect($dashboardUrl)->with('status', 'Your email address has already been verified.');
    }
}
