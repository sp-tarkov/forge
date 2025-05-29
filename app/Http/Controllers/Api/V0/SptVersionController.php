<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\SptVersionResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Models\SptVersion;
use App\Support\Api\V0\QueryBuilder\SptVersionQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SptVersionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // TODO: add url params
        // TODO: test filters, sorts, and fields
        // TODO: add api docs stuff

        $queryBuilder = new SptVersionQueryBuilder()
            ->withFilters($request->input('filter'))
            ->withIncludes($request->string('include')->explode(',')->toArray())
            ->withFields($request->string('fields')->explode(',')->toArray())
            ->withSorts($request->string('sort')->explode(',')->toArray());

        $sptVersions = $queryBuilder->paginate($request->integer('per_page', 12));

        return ApiResponse::success(SptVersionResource::collection($sptVersions));
    }
}
