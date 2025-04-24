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
     * Get Mods
     *
     * Retrieves a paginated list of mods, allowing filtering, sorting, and relationship inclusion.
     *
     * Fields available:<br /><code>hub_id, name, slug, teaser, thumbnail, downloads, source_code_link, detail_url,
     * featured, contains_ai_content, contains_ads, published_at, created_at, updated_at</code>
     *
     * <aside class="notice">This endpoint only offers limited version information. Only the latest 6 versions will be
     * included. For additional version information, use the <code>mod/{id}/versions</code> endpoint.</aside>
     *
     * @response status=200 scenario="Success (All fields, No Includes)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 1,
     *              "hub_id": null,
     *              "name": "Recusandae velit incidunt.",
     *              "slug": "recusandae-velit-incidunt",
     *              "teaser": "Minus est minima quibusdam necessitatibus inventore iste.",
     *              "thumbnail": "",
     *              "downloads": 55212644,
     *              "source_code_link": "http://oconnell.com/earum-sed-fugit-corrupti",
     *              "detail_url": https://forge.sp-tarkov.com/mods/1/recusandae-velit-incidunt,
     *              "featured": true,
     *              "contains_ads": true,
     *              "contains_ai_content": false,
     *              "published_at": "2025-01-09T17:48:53.000000Z",
     *              "created_at": "2024-12-11T14:48:53.000000Z",
     *              "updated_at": "2025-04-10T13:50:00.000000Z"
     *          },
     *          {
     *              "id": 2,
     *              "hub_id": null,
     *              "name": "Adipisci iusto voluptas nihil.",
     *              "slug": "adipisci-iusto-voluptas-nihil",
     *              "teaser": "Minima adipisci perspiciatis nemo maiores rem porro natus.",
     *              "thumbnail": "",
     *              "downloads": 219598104,
     *              "source_code_link": "http://baumbach.net/",
     *              "detail_url": https://forge.sp-tarkov.com/mods/2/adipisci-iusto-voluptas-nihil,
     *              "featured": false,
     *              "contains_ads": true,
     *              "contains_ai_content": true,
     *              "published_at": "2024-08-30T14:48:53.000000Z",
     *              "created_at": "2024-06-22T04:48:53.000000Z",
     *              "updated_at": "2025-04-10T13:50:21.000000Z"
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
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'name,slug,featured,created_at')]
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
    #[UrlParam('query', description: 'Search query to filter mods using Meilisearch. This will search across name, slug, and description fields.', required: false, example: 'raid time')]
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
            ->withSorts($request->string('sort')->explode(',')->toArray())
            ->withSearch($request->string('query')->toString());

        $mods = $queryBuilder->paginate($request->integer('per_page', 12));

        return ApiResponse::success(ModResource::collection($mods));
    }

    /**
     * Get Mod Details
     *
     * Retrieves details for a single mod, allowing relationship inclusion.
     *
     * Fields available:<br /><code>hub_id, name, slug, teaser, description, thumbnail, downloads, source_code_link,
     * detail_url, featured, contains_ai_content, contains_ads, published_at, created_at, updated_at</code>
     *
     * <aside class="notice">This endpoint only offers limited version information. Only the latest 6 versions will be
     * included. For additional version information, use the <code>mod/{id}/versions</code> endpoint.</aside>
     *
     * @response status=200 scenario="Success (All fields, No Includes)"
     *  {
     *      "success": true,
     *      "data": {
     *          "id": 2,
     *          "hub_id": null,
     *          "name": "Adipisci iusto voluptas nihil.",
     *          "slug": "adipisci-iusto-voluptas-nihil",
     *          "teaser": "Minima adipisci perspiciatis nemo maiores rem porro natus.",
     *          "thumbnail": "",
     *          "downloads": 219598104,
     *          "description": "Adipisci rerum minima maiores sed. Neque totam quia libero exercitationem ullam.",
     *          "source_code_link": "http://baumbach.net/",
     *          "detail_url": https://forge.sp-tarkov.com/mods/2/adipisci-iusto-voluptas-nihil,
     *          "featured": false,
     *          "contains_ads": true,
     *          "contains_ai_content": true,
     *          "published_at": "2024-08-30T14:48:53.000000Z",
     *          "created_at": "2024-06-22T04:48:53.000000Z",
     *          "updated_at": "2025-04-10T13:50:21.000000Z"
     *      }
     *  }
     * @response status=404 scenario="Mod Does Not Exist"
     *  {
     *      "success": false,
     *      "code": "NOT_FOUND",
     *      "message": "Resource not found."
     *  }
     */
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'name,slug,featured,created_at')]
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
