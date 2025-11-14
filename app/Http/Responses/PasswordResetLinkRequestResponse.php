<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetLinkRequestResponse implements FailedPasswordResetLinkRequestResponseContract, SuccessfulPasswordResetLinkRequestResponseContract
{
    public function __construct(protected string $status) {}

    /**
     * Create an HTTP response that represents the object.
     *
     * Always returns a success message to prevent user enumeration attacks.
     *
     * @param  Request  $request
     */
    public function toResponse(mixed $request): Response
    {
        // Use the ambiguous success message regardless of actual status
        $message = trans('passwords.sent');

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => $message], 200);
        }

        return back()->with('status', $message);
    }
}
