<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponses
{
    protected function success(string $message, ?array $data = []): JsonResponse
    {
        return $this->baseResponse(message: $message, data: $data, code: 200);
    }

    private function baseResponse(?string $message = '', ?array $data = [], ?int $code = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error(string $message, int $code): JsonResponse
    {
        return $this->baseResponse(message: $message, code: $code);
    }
}
