<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponses
{
    /**
     * Return a success JSON response.
     *
     * @param  array<mixed>  $data
     */
    protected function success(string $message, ?array $data = []): JsonResponse
    {
        return $this->baseResponse(message: $message, data: $data, code: 200);
    }

    /**
     * The base response.
     *
     * @param  array<mixed>  $data
     */
    private function baseResponse(?string $message = '', ?array $data = [], ?int $code = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return an error JSON response.
     */
    protected function error(string $message, int $code): JsonResponse
    {
        return $this->baseResponse(message: $message, code: $code);
    }
}
