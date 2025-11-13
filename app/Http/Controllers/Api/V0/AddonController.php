<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\AddonResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Support\Api\V0\QueryBuilder\AddonQueryBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\UrlParam;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Addons
 *
 * Endpoints for managing and retrieving addons.
 */
class AddonController extends Controller
{
    /**
     * Get Addons
     *
     * Retrieves a paginated list of addons, allowing filtering, sorting, and relationship inclusion.
     *
     * Fields available:<br /><code>guid, name, slug, teaser, thumbnail, downloads, detail_url,
     * contains_ai_content, contains_ads, mod_id, published_at, created_at, updated_at</code>
     *
     * <aside class="notice">This endpoint only offers limited version information. Only the latest 6 versions will be
     * included. For additional version information, use the <code>addon/{id}/versions</code> endpoint.</aside>
     *
     * @response status=200 scenario="Success (All fields, No Includes)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 1,
     *              "guid": "com.example.music-pack",
     *              "name": "Ultimate Music Pack",
     *              "slug": "ultimate-music-pack",
     *              "teaser": "A collection of atmospheric music tracks",
     *              "thumbnail": "",
     *              "downloads": 1523,
     *              "source_code_links": [],
     *              "detail_url": "https://forge.sp-tarkov.com/addon/1/ultimate-music-pack",
     *              "contains_ads": false,
     *              "contains_ai_content": false,
     *              "mod_id": 5,
     *              "is_detached": false,
     *              "published_at": "2025-01-09T17:48:53.000000Z",
     *              "created_at": "2024-12-11T14:48:53.000000Z",
     *              "updated_at": "2025-04-10T13:50:00.000000Z"
     *          }
     *      ],
     *      "links": {
     *          "first": "https://forge.test/api/v0/addons?page=1",
     *          "last": "https://forge.test/api/v0/addons?page=1",
     *          "prev": null,
     *          "next": null
     *      },
     *      "meta": {
     *          "current_page": 1,
     *          "from": 1,
     *          "last_page": 1,
     *          "links": [
     *              {
     *                  "url": null,
     *                  "label": "&laquo; Previous",
     *                  "active": false
     *              },
     *              {
     *                  "url": "https://forge.test/api/v0/addons?page=1",
     *                  "label": "1",
     *                  "active": true
     *              },
     *              {
     *                  "url": null,
     *                  "label": "Next &raquo;",
     *                  "active": false
     *              }
     *          ],
     *          "path": "https://forge.test/api/v0/addons",
     *          "per_page": 12,
     *          "to": 1,
     *          "total": 1
     *      }
     *  }
     * @response status=401 scenario="Unauthenticated"
     *  {
     *      "success": false,
     *      "code": "UNAUTHENTICATED",
     *      "message": "Unauthenticated."
     *  }
     */
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'name,slug,created_at')]
    #[UrlParam('filter[id]', description: 'Filter by comma-separated Addon IDs.', required: false, example: '1,5,10')]
    #[UrlParam('filter[guid]', description: 'Filter by comma-separated GUIDs.', required: false, example: 'com.example.addon1,com.example.addon2')]
    #[UrlParam('filter[name]', description: 'Filter by name (fuzzy filter).', required: false, example: 'Music Pack')]
    #[UrlParam('filter[slug]', description: 'Filter by slug (fuzzy filter).', required: false, example: 'some-addon')]
    #[UrlParam('filter[teaser]', description: 'Filter by teaser text (fuzzy filter).', required: false, example: 'important')]
    #[UrlParam('filter[mod_id]', description: 'Filter by comma-separated mod IDs (parent mod).', required: false, example: '1,2,3')]
    #[UrlParam('filter[contains_ads]', type: 'boolean', description: 'Filter by contains_ads status (1, true, 0, false).', required: false, example: 'false')]
    #[UrlParam('filter[contains_ai_content]', type: 'boolean', description: 'Filter by contains_ai_content status (1, true, 0, false).', required: false, example: 'false')]
    #[UrlParam('filter[is_detached]', type: 'boolean', description: 'Filter by detached status (1, true, 0, false).', required: false, example: 'false')]
    #[UrlParam('filter[created_between]', description: 'Filter by creation date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[updated_between]', description: 'Filter by update date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[published_between]', description: 'Filter by publication date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('query', description: 'Search query to filter addons using Meilisearch. This will search across name, slug, and description fields.', required: false, example: 'music pack')]
    #[UrlParam('include', description: 'Comma-separated list of relationships. Available: `owner`, `additional_authors`, `versions`, `license`, `mod`, `source_code_links`.', required: false, example: 'owner,versions')]
    #[UrlParam('sort', description: 'Sort results by attribute(s). Default ASC. Prefix with `-` for DESC. Comma-separate multiple fields. Allowed: `name`, `created_at`, `updated_at`, `published_at`.', required: false, example: '-name')]
    #[UrlParam('page', type: 'integer', description: 'The page number for pagination.', required: false, example: 2)]
    #[UrlParam('per_page', type: 'integer', description: 'The number of results per page (max 50).', required: false, example: 25)]
    public function index(Request $request): JsonResponse
    {
        $queryBuilder = (new AddonQueryBuilder)
            ->withFilters($request->input('filter'))
            ->withIncludes($request->string('include')->explode(',')->all())
            ->withFields($request->string('fields')->explode(',')->all())
            ->withSorts($request->string('sort')->explode(',')->all())
            ->withSearch($request->string('query')->toString());

        $addons = $queryBuilder->paginate($request->integer('per_page', 12));

        return ApiResponse::success(AddonResource::collection($addons));
    }

    /**
     * Get Addon Details
     *
     * Retrieves details for a single addon, allowing relationship inclusion.
     *
     * Fields available:<br /><code>guid, name, slug, teaser, description, thumbnail, downloads, source_code_links,
     * detail_url, contains_ai_content, contains_ads, mod_id, is_detached, published_at, created_at, updated_at</code>
     *
     * <aside class="notice">This endpoint only offers limited version information. Only the latest 6 versions will be
     * included. For additional version information, use the <code>addon/{id}/versions</code> endpoint.</aside>
     *
     * @response status=200 scenario="Success (All fields, No Includes)"
     *  {
     *      "success": true,
     *      "data": {
     *          "id": 1,
     *          "guid": "com.example.music-pack",
     *          "name": "Ultimate Music Pack",
     *          "slug": "ultimate-music-pack",
     *          "teaser": "A collection of atmospheric music tracks",
     *          "description": "This addon adds over 50 new music tracks...",
     *          "thumbnail": "",
     *          "downloads": 1523,
     *          "source_code_links": [],
     *          "detail_url": "https://forge.sp-tarkov.com/addon/1/ultimate-music-pack",
     *          "contains_ads": false,
     *          "contains_ai_content": false,
     *          "mod_id": 5,
     *          "is_detached": false,
     *          "published_at": "2025-01-09T17:48:53.000000Z",
     *          "created_at": "2024-12-11T14:48:53.000000Z",
     *          "updated_at": "2025-04-10T13:50:00.000000Z"
     *      }
     *  }
     * @response status=404 scenario="Addon Does Not Exist"
     *  {
     *      "success": false,
     *      "code": "NOT_FOUND",
     *      "message": "Resource not found."
     *  }
     */
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'name,slug,created_at')]
    #[UrlParam('include', description: 'Comma-separated list of relationships. Available: `owner`, `additional_authors`, `versions`, `license`, `mod`, `source_code_links`.', required: false, example: 'owner,versions')]
    public function show(Request $request, int $addonId): JsonResponse
    {
        $queryBuilder = (new AddonQueryBuilder)
            ->withIncludes($request->string('include')->explode(',')->all())
            ->withFields($request->string('fields')->explode(',')->all());

        try {
            $addon = $queryBuilder->findOrFail($addonId);
        } catch (ModelNotFoundException) {
            return ApiResponse::error(
                'Resource not found.',
                Response::HTTP_NOT_FOUND,
                ApiErrorCode::NOT_FOUND
            );
        }

        return ApiResponse::success(new AddonResource($addon));
    }
}
