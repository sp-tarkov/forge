<?php

declare(strict_types=1);

namespace App\Exceptions\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Responses\Api\V0\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler
{
    /**
     * Render the API exception into an HTTP response.
     */
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if ($e instanceof ValidationException) {
            return ApiResponse::error(
                'Validation failed.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ApiErrorCode::VALIDATION_FAILED,
                $e->errors()
            );
        }

        if ($e instanceof NotFoundHttpException) {
            return ApiResponse::error(
                'Resource not found.',
                Response::HTTP_NOT_FOUND,
                ApiErrorCode::NOT_FOUND,
            );
        }

        if ($e instanceof AuthenticationException) {
            return ApiResponse::error(
                'Unauthenticated.',
                Response::HTTP_UNAUTHORIZED,
                ApiErrorCode::UNAUTHENTICATED
            );
        }

        if ($e instanceof InvalidQuery) {
            return ApiResponse::error(
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::INVALID_QUERY_PARAMETER
            );
        }

        // Generic fallbacks for other exceptions.
        $statusCode = $this->determineStatusCode($e);
        $message = $this->determineMessage($e, $statusCode);
        $errorCode = match ($statusCode) {
            Response::HTTP_FORBIDDEN => ApiErrorCode::FORBIDDEN,
            Response::HTTP_UNPROCESSABLE_ENTITY => ApiErrorCode::VALIDATION_FAILED,
            Response::HTTP_INTERNAL_SERVER_ERROR => ApiErrorCode::SERVER_ERROR,
            default => ApiErrorCode::UNEXPECTED_ERROR,
        };

        return ApiResponse::error($message, $statusCode, $errorCode);
    }

    /**
     * Determine the HTTP status code for the exception.
     */
    protected function determineStatusCode(Throwable $e): int
    {
        $statusCode = match (true) {
            method_exists($e, 'getStatusCode') => $e->getStatusCode(),
            (is_int($e->getCode()) && $e->getCode() >= 100 && $e->getCode() < 600) => $e->getCode(),
            default => Response::HTTP_INTERNAL_SERVER_ERROR, // Default to 500
        };

        // Ensure the status code is a valid HTTP status code key in the Symfony Response class.
        return array_key_exists($statusCode, Response::$statusTexts)
            ? $statusCode
            : Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    /**
     * Determine the error message for the exception.
     */
    protected function determineMessage(Throwable $e, int $statusCode): string
    {
        // If it's a server error and debug mode is off, use a generic message
        if ($statusCode === Response::HTTP_INTERNAL_SERVER_ERROR && ! config('app.debug')) {
            return 'An unexpected error occurred.';
        }

        // Otherwise, use the exception's message
        return $e->getMessage();
    }
}
