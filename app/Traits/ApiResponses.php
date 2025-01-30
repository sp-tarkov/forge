<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponses
{
    /**
     * Return a success JSON response.
     */
    protected function success(string $message, ?array $data = []): JsonResponse
    {
        return $this->baseResponse(message: $message, data: $data, code: 200);
    }

    /**
     * The base response.
     */
    private function baseResponse(?string $message = '', ?array $data = [], ?int $code = 200): JsonResponse
    {
        $response = [];
        $response['message'] = $message;
        if ($data) {
            $response['data'] = $data;
        }

        $response['status'] = $code;

        return response()->json($response, $code);
    }

    /**
     * Return an error JSON response.
     */
    protected function error(string $message, int $code): JsonResponse
    {
        return $this->baseResponse(message: $message, code: $code);
    }
}
