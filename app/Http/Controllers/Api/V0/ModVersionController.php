<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\ModVersionResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Support\Api\V0\QueryBuilder\ModVersionQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * @group Mods
 */
class ModVersionController extends Controller
{
    /**
     * List Mod Versions
     *
     * Retrieves a paginated list of mod versions, allowing filtering, sorting, and relationship inclusion.
     *
     * Fields available:<br /><code>id, hub_id, version, description, link, spt_version_constraint, virus_total_link,
     * downloads, published_at, created_at, updated_at</code>
     *
     * @response status=200 scenario="Success (All fields, No Includes)"
     *  {
     *  }
     * @response status=401 scenario="Unauthenticated"
     *  {
     *      "success": false,
     *      "code": "UNAUTHENTICATED",
     *      "message": "Unauthenticated."
     *  }
     */
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'id,version,link,created_at')]
    #[UrlParam('filter[id]', description: 'Filter by mod version ID. Comma-separated.', required: false, example: '234,432')]
    #[UrlParam('filter[hub_id]', description: 'Filter by mod hub ID. Comma-separated.', required: false, example: '234,432')]
    #[UrlParam('filter[version]', description: 'Filter mod versions by using a SemVer constraint.', required: false, example: '^1.0.0')]
    #[UrlParam('filter[description]', description: 'Fuzzy-filter by mod version description.', required: false, example: 'This is a description')]
    #[UrlParam('filter[link]', description: 'Filter by mod version link.', required: false, example: 'example.com')]
    #[UrlParam('filter[virus_total_link]', description: 'Filter by mod version virus total link.', required: false, example: 'example.com')]
    #[UrlParam('filter[published_between]', description: 'Filter by mod version published between.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[created_between]', description: 'Filter by mod version created between.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[updated_between]', description: 'Filter by mod version updated between.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[spt_version]', description: 'Filter mod versions that are compatible with a SemVer constraint.', required: false, example: '^3.8.0')]
    #[UrlParam('include', description: 'Comma-separated list of relationships. Available: dependencies.', required: false, example: 'dependencies')]
    #[UrlParam('sort', description: 'Sort results by attribute(s). Default ASC. Prefix with `-` for DESC. Comma-separate multiple fields.', required: false, example: '-version,-created_at')]
    #[UrlParam('page', type: 'integer', description: 'The page number for pagination.', required: false, example: 2)]
    #[UrlParam('per_page', type: 'integer', description: 'The number of results per page (max 50).', required: false, example: 25)]
    public function index(Request $request, int $modId): JsonResponse
    {
        $queryBuilder = (new ModVersionQueryBuilder($modId))
            ->withFilters($request->input('filter'))
            ->withIncludes($request->string('include')->explode(',')->toArray())
            ->withFields($request->string('fields')->explode(',')->toArray())
            ->withSorts($request->string('sort')->explode(',')->toArray());

        $modVersions = $queryBuilder->paginate($request->integer('per_page', 12));

        return ApiResponse::success(ModVersionResource::collection($modVersions));
    }
}
