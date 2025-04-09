<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\ModResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Support\Api\V0\QueryBuilder\ModQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * @group Mods
 *
 * Endpoints for managing and retrieving mods.
 */
class ModController extends Controller
{
    /**
     * List Mods
     *
     * Retrieves a paginated list of mods, allowing filtering, sorting, and relationship inclusion.
     *
     * Fields available:<br /><code>id, hub_id, name, slug, teaser, source_code_link, featured, contains_ads,
     * contains_ai_content, published_at, created_at, updated_at</code>
     *
     * <aside class="notice">This endpoint only offers limited version information. Only the latest 6 versions will be
     * included. For additional version information, use the <code>mod/{id}/versions</code> endpoint.</aside>
     *
     * @response status=200 scenario="Success (All fields, No Includes)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 32,
     *              "hub_id": 79,
     *              "name": "Kiki-BiggerStash",
     *              "slug": "kiki-biggerstash",
     *              "teaser": "Finally you can Horde it all!",
     *              "source_code_link": "https://github.com/kieran-boyle/Mods/tree/master/Kiki-BiggerStash",
     *              "featured": false,
     *              "contains_ads": false,
     *              "contains_ai_content": false,
     *              "published_at": "2021-01-02T16:42:14.000000Z",
     *              "created_at": "2021-01-02T16:42:14.000000Z",
     *              "updated_at": "2024-07-13T12:11:38.000000Z"
     *          },
     *          {
     *              "id": 42,
     *              "hub_id": 98,
     *              "name": "Speed Loader",
     *              "slug": "speed-loader",
     *              "teaser": "Allows large capacity magazines to be loaded/ unloaded with ammo faster.",
     *              "source_code_link": "",
     *              "featured": false,
     *              "contains_ads": false,
     *              "contains_ai_content": false,
     *              "published_at": "2021-01-17T18:46:27.000000Z",
     *              "created_at": "2021-01-17T18:46:27.000000Z",
     *              "updated_at": "2023-03-12T10:48:20.000000Z"
     *          }
     *      ],
     *      "links": {
     *          "first": "https://forge.test/api/v0/mods?page=1",
     *          "last": "https://forge.test/api/v0/mods?page=1",
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
     *                  "url": "https://forge.test/api/v0/mods?page=1",
     *                  "label": "1",
     *                  "active": true
     *              },
     *              {
     *                  "url": null,
     *                  "label": "Next &raquo;",
     *                  "active": false
     *              }
     *          ],
     *          "path": "https://forge.test/api/v0/mods",
     *          "per_page": 12,
     *          "to": 2,
     *          "total": 2
     *      }
     *  }
     * @response status=401 scenario="Unauthenticated"
     *  {
     *      "success": false,
     *      "code": "UNAUTHENTICATED",
     *      "message": "Unauthenticated."
     *  }
     */
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'id,name,slug,featured,created_at')]
    #[UrlParam('filter[id]', description: 'Filter by comma-separated Mod IDs.', required: false, example: '1,5,10')]
    #[UrlParam('filter[hub_id]', description: 'Filter by comma-separated Hub IDs.', required: false, example: '123,456')]
    #[UrlParam('filter[name]', description: 'Filter by name (fuzzy filter).', required: false, example: 'Raid Time')]
    #[UrlParam('filter[slug]', description: 'Filter by slug (fuzzy filter).', required: false, example: 'some-mod')]
    #[UrlParam('filter[teaser]', description: 'Filter by teaser text (fuzzy filter).', required: false, example: 'important')]
    #[UrlParam('filter[source_code_link]', description: 'Filter by source code link (fuzzy filter).', required: false, example: 'github.com')]
    #[UrlParam('filter[featured]', description: 'Filter by featured status (1, true, 0, false).', required: false, example: 'true')]
    #[UrlParam('filter[contains_ads]', type: 'boolean', description: 'Filter by contains_ads status (1, true, 0, false).', required: false, example: 'false')]
    #[UrlParam('filter[contains_ai_content]', type: 'boolean', description: 'Filter by contains_ai_content status (1, true, 0, false).', required: false, example: 'false')]
    #[UrlParam('filter[created_between]', description: 'Filter by creation date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[updated_between]', description: 'Filter by update date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[published_between]', description: 'Filter by publication date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[spt_version]', description: 'Filter mods that are compatible with an SPT version SemVer constraint. This will only filter the mods, not the mod versions.', required: false, example: '^3.8.0')]
    #[UrlParam('include', description: 'Comma-separated list of relationships. Available: `owner`, `authors`, `versions`, `versions`, `license`.', required: false, example: 'owner,versions')]
    #[UrlParam('sort', description: 'Sort results by attribute(s). Default ASC. Prefix with `-` for DESC. Comma-separate multiple fields. Allowed: `id`, `name`, `slug`, `created_at`, `updated_at`, `published_at`.', required: false, example: '-created_at,name')]
    #[UrlParam('page', type: 'integer', description: 'The page number for pagination.', required: false, example: 2)]
    #[UrlParam('per_page', type: 'integer', description: 'The number of results per page (max 50).', required: false, example: 25)]
    public function index(Request $request): JsonResponse
    {
        $queryBuilder = (new ModQueryBuilder)
            ->withFilters($request->input('filter'))
            ->withIncludes($request->string('include')->explode(',')->toArray())
            ->withFields($request->string('fields')->explode(',')->toArray())
            ->withSorts($request->string('sort')->explode(',')->toArray());

        $mods = $queryBuilder->paginate($request->integer('per_page', 12));

        return ApiResponse::success(ModResource::collection($mods));
    }

    /**
     * Show Mod Details
     *
     * Retrieves details for a single mod, allowing relationship inclusion.
     *
     * Fields available:<br /><code>id, hub_id, name, slug, teaser, source_code_link, featured, contains_ads,
     * contains_ai_content, published_at, created_at, updated_at</code>
     *
     * <aside class="notice">This endpoint only offers limited version information. Only the latest 6 versions will be
     * included. For additional version information, use the <code>mod/{id}/versions</code> endpoint.</aside>
     *
     * @response status=200 scenario="Success (All fields, No Includes)"
     *  {
     *      "success": true,
     *      "data": {
     *          "id": 32,
     *          "hub_id": 79,
     *          "name": "Kiki-BiggerStash",
     *          "slug": "kiki-biggerstash",
     *          "teaser": "Finally you can Horde it all!",
     *          "description": "Set your desired stash sizes in config.json.",
     *          "source_code_link": "https://github.com/kieran-boyle/Mods/tree/master/Kiki-BiggerStash",
     *          "featured": false,
     *          "contains_ads": false,
     *          "contains_ai_content": false,
     *          "published_at": "2021-01-02T16:42:14.000000Z",
     *          "created_at": "2021-01-02T16:42:14.000000Z",
     *          "updated_at": "2024-07-13T12:11:38.000000Z"
     *      }
     *  }
     * @response status=404 scenario="Mod Does Not Exist"
     *  {
     *      "success": false,
     *      "code": "NOT_FOUND",
     *      "message": "Resource not found."
     *  }
     */
    #[UrlParam('mod', type: 'integer', description: 'The ID of the Mod.', required: true, example: 234)]
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'id,name,slug,featured,created_at')]
    #[UrlParam('include', description: 'Comma-separated list of relationships. Available: `owner`, `authors`, `versions`, `license`.', required: false, example: 'owner,versions')]
    public function show(Request $request, int $modId): JsonResponse
    {
        $queryBuilder = (new ModQueryBuilder)
            ->withIncludes($request->string('include')->explode(',')->toArray())
            ->withFields($request->string('fields')->explode(',')->toArray());

        $mod = $queryBuilder->findOrFail($modId);

        return ApiResponse::success(new ModResource($mod));
    }
}
