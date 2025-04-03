<?php

declare(strict_types=1);

namespace App\Http\Responses\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    /**
     * Return a success response.
     *
     * @param  mixed  $data  Data payload for the response.
     * @param  int  $status  HTTP status code (default: 200 OK).
     * @param  array<string, string>  $headers  Additional headers.
     */
    public static function success(
        mixed $data = [],
        int $status = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        $payload = ['success' => true];

        // Check if the data is a paginated resource collection
        if (
            $data instanceof AnonymousResourceCollection &&
            $data->resource instanceof AbstractPaginator
        ) {
            // If paginated, merge the paginator's structure (data, links, meta)
            $payload = array_merge($payload, $data->response()->getData(true));
        } elseif (is_array($data) || is_object($data) || ! empty($data)) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status, $headers);
    }

    /**
     * Return an error response.
     *
     * @param  string  $message  Error message.
     * @param  int  $status  HTTP status code.
     * @param  ApiErrorCode|null  $code  Optional machine-readable error code.
     * @param  mixed|null  $errors  Optional additional error details.
     * @param  array<string, string>  $headers  Additional headers.
     */
    public static function error(
        string $message,
        int $status,
        ?ApiErrorCode $code = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        $payload = [
            'success' => false,
            'code' => $code?->value,
            'message' => $message,
        ];

        if (! is_null($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json(array_filter($payload, fn ($value): bool => ! is_null($value)), $status, $headers);
    }
}
