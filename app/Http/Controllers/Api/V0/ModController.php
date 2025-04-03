<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\ModResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Models\Mod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

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
     * @queryParam include string optional Comma-separated list of relationships. Available: `owner`, `authors`, `versions`, 'latest_version', `license`. Example: owner,versions
     * @queryParam filter[id] string optional Filter by comma-separated Mod IDs. Example: 1,5,10
     * @queryParam filter[hub_id] string optional Filter by comma-separated Hub IDs. Example: 123,456
     * @queryParam filter[name] string optional Filter by name (fuzzy filter). Example: Custom
     * @queryParam filter[slug] string optional Filter by slug (fuzzy filter). Example: some-mod
     * @queryParam filter[teaser] string optional Filter by teaser text (fuzzy filter). Example: important
     * @queryParam filter[source_code_link] string optional Filter by source code link (fuzzy filter). Example: github.com
     * @queryParam filter[featured] boolean optional Filter by featured status (1, true, 0, false). Example: 1
     * @queryParam filter[contains_ads] boolean optional Filter by contains_ads status (1, true, 0, false). Example: 0
     * @queryParam filter[contains_ai_content] boolean optional Filter by contains_ai_content status (1, true, 0, false). Example: false
     * @queryParam filter[created_between] string optional Filter by creation date range (YYYY-MM-DD,YYYY-MM-DD). Example: 2025-01-01,2025-03-31
     * @queryParam filter[updated_between] string optional Filter by update date range (YYYY-MM-DD,YYYY-MM-DD). Example: 2025-04-01,2025-04-03
     * @queryParam filter[published_between] string optional Filter by publication date range (YYYY-MM-DD,YYYY-MM-DD). Example: 2025-01-01,
     * @queryParam sort string optional Sort results by attribute(s). Default ASC. Prefix with '-' for DESC. Comma-separate multiple fields. Allowed: `id`, `name`, `slug`, `created_at`, `updated_at`, `published_at`. Example: -created_at,name
     * @queryParam page int optional The page number for pagination. Example: 2
     * @queryParam per_page int optional The number of results per page (max 50). Example: 25
     *
     * @response status=200 scenario="Success (No Includes)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 15,
     *              "hub_id": 1234,
     *              "owner": null,
     *              "name": "Awesome Utility Mod",
     *              "slug": "awesome-utility-mod",
     *              "teaser": "Makes things much more awesome.",
     *              "source_code_link": "https://github.com/user/awesome-utility-mod",
     *              "featured": true,
     *              "contains_ads": false,
     *              "contains_ai_content": false,
     *              "published_at": "2025-01-15T10:00:00.000000Z",
     *              "created_at": "2025-01-10T08:30:00.000000Z",
     *              "updated_at": "2025-03-20T15:45:10.000000Z",
     *              "authors": [],
     *              "versions": [],
     *              "latest_version": null,
     *              "license": null
     *          },
     *          {
     *              "id": 21,
     *              "hub_id": 5678,
     *              "owner": null,
     *              "name": "Better AI",
     *              "slug": "better-ai",
     *              "teaser": "Improves AI behaviour significantly.",
     *              "source_code_link": "https://gitlab.com/user/better-ai",
     *              "featured": false,
     *              "contains_ads": false,
     *              "contains_ai_content": false,
     *              "published_at": "2025-02-01T12:00:00.000000Z",
     *              "created_at": "2025-02-01T11:00:00.000000Z",
     *              "updated_at": "2025-02-28T18:00:05.000000Z",
     *              "authors": [],
     *              "versions": [],
     *              "latest_version": null,
     *              "license": null
     *          }
     *      ],
     *      "links": {
     *          "first": "http://localhost/api/v0/mods?page=1",
     *          "last": "http://localhost/api/v0/mods?page=5",
     *          "prev": null,
     *          "next": "http://localhost/api/v0/mods?page=2"
     *      },
     *      "meta": {
     *          "current_page": 1,
     *          "from": 1,
     *          "last_page": 2,
     *          "links": [
     *              {
     *                  "url": null,
     *                  "label": "&laquo; Previous",
     *                  "active": false
     *              },
     *              {
     *                  "url": "http://localhost/api/v0/mods?page=1",
     *                  "label": "1",
     *                  "active": true
     *              },
     *              {
     *                  "url": "http://localhost/api/v0/mods?page=2",
     *                  "label": "2",
     *                  "active": false
     *              }
     *              {
     *                  "url": "http://localhost/api/v0/mods?page=3",
     *                  "label": "3",
     *                  "active": false
     *              }
     *              {
     *                  "url": "http://localhost/api/v0/mods?page=4",
     *                  "label": "4",
     *                  "active": false
     *              }
     *              {
     *                  "url": "http://localhost/api/v0/mods?page=5",
     *                  "label": "5",
     *                  "active": false
     *              }
     *              {
     *                  "url": "http://localhost/api/v0/mods?page=2",
     *                  "label": "Next &raquo;",
     *                  "active": false
     *              }
     *          ],
     *          "path": "http://localhost/api/v0/mods",
     *          "per_page": 2,
     *          "to": 2,
     *          "total": 10
     *      }
     *  }
     * @response status=401 scenario="Unauthenticated"
     *  {
     *      "success": false,
     *      "code": "UNAUTHENTICATED",
     *      "message": "Unauthenticated."
     *  }
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', '12'), 50);

        $mods = QueryBuilder::for(Mod::class)
            ->allowedFilters([
                AllowedFilter::exact('id'),
                AllowedFilter::exact('hub_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('slug'),
                AllowedFilter::partial('teaser'),
                AllowedFilter::partial('source_code_link'),
                AllowedFilter::exact('featured'),
                AllowedFilter::exact('contains_ads'),
                AllowedFilter::exact('contains_ai_content'),
                AllowedFilter::scope('created_between', 'createdAtBetween'),
                AllowedFilter::scope('updated_between', 'updatedAtBetween'),
                AllowedFilter::scope('published_between', 'publishedAtBetween'),
            ])
            ->allowedIncludes([
                'owner',
                'authors',
                'versions',
                AllowedInclude::relationship('latestVersion', 'latest_version'),
                'license',
            ])
            ->allowedSorts(['id', 'name', 'slug', 'created_at', 'updated_at', 'published_at'])
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(ModResource::collection($mods));
    }

    public function show(Mod $mod): JsonResponse {}
}
