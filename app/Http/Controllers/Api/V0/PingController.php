<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\V0\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group General
 *
 * APIs for general application status and information.
 */
class PingController extends Controller
{
    /**
     * Check API Health
     *
     * Returns a simple 'pong' message to indicate that the API endpoint
     * is available and responding correctly. This endpoint is typically used
     * for health checks or basic connectivity tests.
     *
     * It does not require authentication.
     *
     * @unauthenticated
     *
     * @response status=200 scenario="Successful Ping"
     *  {
     *      "success": true,
     *      "data": {
     *          "message": "pong"
     *      }
     *  }
     */
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success(['message' => 'pong']);
    }
}
