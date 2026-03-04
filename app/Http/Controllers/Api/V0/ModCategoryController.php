<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\ModCategoryResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Support\Api\V0\QueryBuilder\ModCategoryQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\UrlParam;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Mod Categories
 *
 * Endpoints for retrieving mod category data.
 */
class ModCategoryController extends Controller
{
    /**
     * Get Mod Categories
     *
     * Retrieves a paginated list of mod categories, allowing filtering and sorting.
     *
     * Fields available:<br /><code>id, hub_id, title, slug, description</code>
     *
     * @response status=200 scenario="Success"
     * {
     *     "success": true,
     *     "data": [
     *         {
     *             "id": 1,
     *             "hub_id": 12,
     *             "title": "Weapons",
     *             "slug": "weapons",
     *             "description": "Weapon mods and attachments",
     *         },
     *         {
     *             "id": 2,
     *             "hub_id": 13,
     *             "title": "Gear",
     *             "slug": "gear",
     *             "description": "Armor, rigs, and equipment",
     *         }
     *     ],
     *     "links": {
     *         "first": "https://forge.sp-tarkov.com/api/v0/mod-categories?page=1",
     *         "last": "https://forge.sp-tarkov.com/api/v0/mod-categories?page=1",
     *         "prev": null,
     *         "next": null
     *     },
     *     "meta": {
     *         "current_page": 1,
     *         "from": 1,
     *         "last_page": 1,
     *         "links": [
     *             {
     *                 "url": null,
     *                 "label": "&laquo; Previous",
     *                 "active": false
     *             },
     *             {
     *                 "url": "https://forge.sp-tarkov.com/api/v0/mod-categories?page=1",
     *                 "label": "1",
     *                 "active": true
     *             },
     *             {
     *                 "url": null,
     *                 "label": "Next &raquo;",
     *                 "active": false
     *             }
     *         ],
     *         "path": "https://forge.sp-tarkov.com/api/v0/mod-categories",
     *         "per_page": 50,
     *         "to": 2,
     *         "total": 2
     *     }
     * }
     * @response status=401 scenario="Unauthenticated"
     * {
     *     "success": false,
     *     "code": "UNAUTHENTICATED",
     *     "message": "Unauthenticated."
     * }
     */
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'id,title,slug')]
    #[UrlParam('filter[id]', description: 'Filter by category ID. Comma-separated.', required: false, example: '1,2,3')]
    #[UrlParam('filter[slug]', description: 'Filter by category slug. Comma-separated.', required: false, example: 'weapons,gear')]
    #[UrlParam('filter[title]', description: 'Filter by category title (wildcard search).', required: false, example: 'weapon')]
    #[UrlParam('sort', description: 'Sort results by attribute(s). Default ASC. Prefix with `-` for DESC. Comma-separate multiple fields.', required: false, example: 'title,-slug')]
    #[UrlParam('page', type: 'integer', description: 'The page number for pagination.', required: false, example: 2)]
    #[UrlParam('per_page', type: 'integer', description: 'The number of results per page (max 100).', required: false, example: 50)]
    public function index(Request $request): JsonResponse
    {
        $sorts = $request->string('sort')->explode(',')->filter()->all();

        // Default to sorting by title when no sort is specified
        if (empty(array_filter($sorts))) {
            $sorts = ['title'];
        }

        $queryBuilder = (new ModCategoryQueryBuilder)
            ->withFilters($request->input('filter'))
            ->withFields($request->string('fields')->explode(',')->all())
            ->withSorts($sorts);

        $categories = $queryBuilder->paginate(min($request->integer('per_page', 50), 100));

        return ApiResponse::success(ModCategoryResource::collection($categories));
    }

    /**
     * Get Mod Category
     *
     * Retrieves a single mod category by ID or slug.
     *
     * @response status=200 scenario="Success"
     * {
     *     "success": true,
     *     "data": {
     *         "id": 1,
     *         "hub_id": 12,
     *         "title": "Weapons",
     *         "slug": "weapons",
     *         "description": "Weapon mods and attachments",
     *     }
     * }
     * @response status=404 scenario="Not Found"
     * {
     *     "success": false,
     *     "code": "NOT_FOUND",
     *     "message": "The requested resource was not found."
     * }
     */
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'id,title,slug')]
    public function show(Request $request, string $identifier): JsonResponse
    {
        $queryBuilder = (new ModCategoryQueryBuilder)
            ->withFields($request->string('fields')->explode(',')->all());

        // Apply the query builder's field selection, then filter by ID or slug
        $query = $queryBuilder->apply();

        if (is_numeric($identifier)) {
            $query->where('mod_categories.id', $identifier);
        } else {
            $query->where('mod_categories.slug', $identifier);
        }

        $category = $query->first();

        if (! $category) {
            return ApiResponse::error('Resource not found.', Response::HTTP_NOT_FOUND, ApiErrorCode::NOT_FOUND);
        }

        return ApiResponse::success(new ModCategoryResource($category));
    }
}
