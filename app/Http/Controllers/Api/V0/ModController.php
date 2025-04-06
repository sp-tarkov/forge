<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\ModResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Models\Mod;
use App\Support\QueryBuilder\Includes\ConditionalVersionsInclude;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\UrlParam;
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
     * @response status=200 scenario="Success (No Includes)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 600,
     *              "hub_id": 817,
     *              "name": "notGreg's Directional Damage Markers",
     *              "slug": "notgregs-directional-damage-markers",
     *              "teaser": "Directional damage markers for Escape from Tarkov.",
     *              "source_code_link": "https://dev.sp-tarkov.com/notGreg/directionalDamageMarkers/",
     *              "featured": false,
     *              "contains_ads": false,
     *              "contains_ai_content": false,
     *              "published_at": "2022-09-03T15:37:32.000000Z",
     *              "created_at": "2022-09-03T15:37:32.000000Z",
     *              "updated_at": "2023-06-02T15:42:07.000000Z"
     *          },
     *          {
     *              "id": 546,
     *              "hub_id": 760,
     *              "name": "Useless Key Blacklist",
     *              "slug": "useless-key-blacklist",
     *              "teaser": "No more wasting precious space on your keytool with keys that have no purpose! Removes useless keys that lead to already-unlocked doors from loot tables, bot equipment, Fence, and the Flea Market.",
     *              "source_code_link": "",
     *              "featured": false,
     *              "contains_ads": false,
     *              "contains_ai_content": false,
     *              "published_at": "2022-08-09T07:08:20.000000Z",
     *              "created_at": "2022-08-09T07:08:20.000000Z",
     *              "updated_at": "2024-07-10T20:44:45.000000Z"
     *          }
     *      ],
     *      "links": {
     *          "first": "https://forge.sp-tarkov.com/api/v0/mods?filter%5Bid%5D=200&page=1",
     *          "last": "https://forge.sp-tarkov.com/api/v0/mods?filter%5Bid%5D=200&page=1",
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
     *                  "url": "https://forge.sp-tarkov.com/api/v0/mods?filter%5Bid%5D=200&page=1",
     *                  "label": "1",
     *                  "active": true
     *              },
     *              {
     *                  "url": null,
     *                  "label": "Next &raquo;",
     *                  "active": false
     *              }
     *          ],
     *          "path": "https://forge.sp-tarkov.com/api/v0/mods",
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
    #[UrlParam('include', description: 'Comma-separated list of relationships. Available: `owner`, `authors`, `versions`, `latest_version`, `license`.', required: false, example: 'owner,versions')]
    #[UrlParam('filter[id]', description: 'Filter by comma-separated Mod IDs.', required: false, example: '1,5,10')]
    #[UrlParam('filter[hub_id]', description: 'Filter by comma-separated Hub IDs.', required: false, example: '123,456')]
    #[UrlParam('filter[name]', description: 'Filter by name (fuzzy filter).', required: false, example: 'Raid Time')]
    #[UrlParam('filter[slug]', description: 'Filter by slug (fuzzy filter).', required: false, example: 'some-mod')]
    #[UrlParam('filter[teaser]', description: 'Filter by teaser text (fuzzy filter).', required: false, example: 'important')]
    #[UrlParam('filter[source_code_link]', description: 'Filter by source code link (fuzzy filter).', required: false, example: 'github.com')]
    #[UrlParam('filter[featured]', description: 'Filter by featured status (1, true, 0, false).', required: false, example: 'true')]
    #[UrlParam('filter[contains_ads]', description: 'Filter by contains_ads status (1, true, 0, false).', required: false, example: 'false')]
    #[UrlParam('filter[contains_ai_content]', description: 'Filter by contains_ai_content status (1, true, 0, false).', required: false, example: 'false')]
    #[UrlParam('filter[spt_version]', description: 'Filter mods compatible with an SPT version SemVer constraint.', required: false, example: '^3.8.0')]
    #[UrlParam('filter[created_between]', description: 'Filter by creation date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[updated_between]', description: 'Filter by update date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[published_between]', description: 'Filter by publication date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('versions_limit', description: 'Limit the number of versions returned in the `versions` relationship (if included). Default is 1. Max is 10.', required: false, example: 5)]
    #[UrlParam('sort', description: 'Sort results by attribute(s). Default ASC. Prefix with `-` for DESC. Comma-separate multiple fields. Allowed: `id`, `name`, `slug`, `created_at`, `updated_at`, `published_at`.', required: false, example: '-created_at,name')]
    #[UrlParam('page', type: 'integer', description: 'The page number for pagination.', required: false, example: 2)]
    #[UrlParam('per_page', type: 'integer', description: 'The number of results per page (max 50).', required: false, example: 25)]
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', '12'), 50);

        $mods = QueryBuilder::for(Mod::apiQueryable())
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
                AllowedFilter::scope('spt_version', 'sptVersion'),
                AllowedFilter::scope('created_between', 'createdAtBetween'),
                AllowedFilter::scope('updated_between', 'updatedAtBetween'),
                AllowedFilter::scope('published_between', 'publishedAtBetween'),
            ])
            ->allowedIncludes([
                AllowedInclude::relationship('owner'),
                AllowedInclude::relationship('authors'),
                AllowedInclude::relationship('license'),
                AllowedInclude::custom('versions', new ConditionalVersionsInclude),
            ])
            ->allowedSorts(['id', 'name', 'slug', 'created_at', 'updated_at', 'published_at'])
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(ModResource::collection($mods));
    }

    public function show(Mod $mod): JsonResponse
    {
        //
    }
}
