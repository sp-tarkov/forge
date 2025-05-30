<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\SptVersionResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Support\Api\V0\QueryBuilder\SptVersionQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * @group SPT
 *
 * Endpoints for retrieving SPT related data.
 */
class SptVersionController extends Controller
{
    /**
     *  Get SPT Versions
     *
     *  Retrieves a paginated list of SPT versions, allowing filtering and sorting.
     *
     *  Fields available:<br /><code>id, version, version_major, version_minor, version_patch,
     *  version_labels, mod_count, link, color_class, created_at, updated_at</code>
     */
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'id,version,created_at')]
    #[UrlParam('filter[id]', description: 'Filter by spt version ID. Comma-separated.', required: false, example: '234,432')]
    #[UrlParam('filter[created_between]', description: 'Filter by spt version created between.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[updated_between]', description: 'Filter by spt version updated between.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[spt_version]', description: 'Filter spt versions that are compatible with a SemVer constraint.', required: false, example: '^3.8.0')]
    #[UrlParam('sort', description: 'Sort results by attribute(s). Default ASC. Prefix with `-` for DESC. Comma-separate multiple fields.', required: false, example: '-version,-created_at')]
    #[UrlParam('page', type: 'integer', description: 'The page number for pagination.', required: false, example: 2)]
    #[UrlParam('per_page', type: 'integer', description: 'The number of results per page (max 50).', required: false, example: 25)]
    public function index(Request $request): JsonResponse
    {
        $queryBuilder = (new SptVersionQueryBuilder)
            ->withFilters($request->input('filter'))
            ->withFields($request->string('fields')->explode(',')->toArray())
            ->withSorts($request->string('sort')->explode(',')->toArray());

        $sptVersions = $queryBuilder->paginate($request->integer('per_page', 12));

        return ApiResponse::success(SptVersionResource::collection($sptVersions));
    }
}
