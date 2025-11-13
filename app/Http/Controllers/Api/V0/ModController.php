<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\ModResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Support\Api\V0\QueryBuilder\ModQueryBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\UrlParam;
use Symfony\Component\HttpFoundation\Response;

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
     * Fields available:<br /><code>hub_id, guid, name, slug, teaser, thumbnail, downloads, detail_url,
     * fika_compatibility, featured, contains_ai_content, contains_ads, category_id, published_at, created_at,
     * updated_at</code>
     *
     * <aside class="notice">This endpoint only offers limited version information. Only the latest 6 versions will be
     * included. For additional version information, use the <code>mod/{id}/versions</code> endpoint.</aside>
     *
     * <aside class="notice">
     * The <code>fika_compatibility</code> field on a mod is a boolean that indicates whether any published version is
     * confirmed compatible with Fika. Version records expose their own <code>fika_compatibility</code> field as a
     * string with one of <code>compatible</code>, <code>incompatible</code>, or <code>unknown</code>.
     * </aside>
     *
     * @response status=200 scenario="Success (All fields, No Includes)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 1,
     *              "hub_id": null,
     *              "guid": "com.oconnell.recusandae-velit-incidunt",
     *              "name": "Recusandae velit incidunt.",
     *              "slug": "recusandae-velit-incidunt",
     *              "teaser": "Minus est minima quibusdam necessitatibus inventore iste.",
     *              "thumbnail": "",
     *              "downloads": 55212644,
     *              "source_code_links": [
     *                  {
     *                      "url": "http://oconnell.com/earum-sed-fugit-corrupti",
     *                      "label": null
     *                  }
     *              ],
     *              "detail_url": "https://forge.sp-tarkov.com/mods/1/recusandae-velit-incidunt",
     *              "fika_compatibility": true,
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
     *              "guid": "com.baumbach.adipisci-iusto-voluptas-nihil",
     *              "name": "Adipisci iusto voluptas nihil.",
     *              "slug": "adipisci-iusto-voluptas-nihil",
     *              "teaser": "Minima adipisci perspiciatis nemo maiores rem porro natus.",
     *              "thumbnail": "",
     *              "downloads": 219598104,
     *              "source_code_links": [
     *                  {
     *                      "url": "http://baumbach.net/",
     *                      "label": null
     *                  }
     *              ],
     *              "detail_url": "https://forge.sp-tarkov.com/mods/2/adipisci-iusto-voluptas-nihil",
     *              "fika_compatibility": false,
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
     * @response status=200 scenario="Success (Include Owner and Category)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 1,
     *              "hub_id": null,
     *              "guid": "com.oconnell.recusandae-velit-incidunt",
     *              "name": "Recusandae velit incidunt.",
     *              "slug": "recusandae-velit-incidunt",
     *              "teaser": "Minus est minima quibusdam necessitatibus inventore iste.",
     *              "thumbnail": "",
     *              "downloads": 55212644,
     *              "source_code_links": [
     *                  {
     *                      "url": "http://oconnell.com/earum-sed-fugit-corrupti",
     *                      "label": null
     *                  }
     *              ],
     *              "detail_url": "https://forge.sp-tarkov.com/mods/1/recusandae-velit-incidunt",
     *              "fika_compatibility": true,
     *              "featured": true,
     *              "contains_ads": true,
     *              "contains_ai_content": false,
     *              "owner": {
     *                  "id": 1,
     *                  "name": "ModAuthor",
     *                  "profile_photo_url": "https://example.com/profile.jpg",
     *                  "cover_photo_url": "https://example.com/cover.jpg"
     *              },
     *              "category": {
     *                  "id": 1,
     *                  "name": "Gameplay",
     *                  "slug": "gameplay",
     *                  "color_class": "blue"
     *              },
     *              "published_at": "2025-01-09T17:48:53.000000Z",
     *              "created_at": "2024-12-11T14:48:53.000000Z",
     *              "updated_at": "2025-04-10T13:50:00.000000Z"
     *          }
     *      ],
     *      "links": {
     *          "first": "https://forge.test/api/v0/mods?include=owner,category&page=1",
     *          "last": "https://forge.test/api/v0/mods?include=owner,category&page=1",
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
     *                  "url": "https://forge.test/api/v0/mods?include=owner,category&page=1",
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
     *          "to": 1,
     *          "total": 1
     *      }
     *  }
     * @response status=200 scenario="Success (Include Versions and License)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 1,
     *              "hub_id": null,
     *              "guid": "com.oconnell.recusandae-velit-incidunt",
     *              "name": "Recusandae velit incidunt.",
     *              "slug": "recusandae-velit-incidunt",
     *              "teaser": "Minus est minima quibusdam necessitatibus inventore iste.",
     *              "thumbnail": "",
     *              "downloads": 55212644,
     *              "source_code_links": [
     *                  {
     *                      "url": "http://oconnell.com/earum-sed-fugit-corrupti",
     *                      "label": null
     *                  }
     *              ],
     *              "detail_url": "https://forge.sp-tarkov.com/mods/1/recusandae-velit-incidunt",
     *              "fika_compatibility": true,
     *              "featured": true,
     *              "contains_ads": true,
     *              "contains_ai_content": false,
     *              "versions": [
     *                  {
     *                      "id": 1,
     *                      "version": "1.2.3",
     *                      "spt_version_constraint": "^3.8.0",
     *                      "downloads": 1523,
     *                      "published_at": "2025-01-09T17:48:53.000000Z"
     *                  },
     *                  {
     *                      "id": 2,
     *                      "version": "1.2.2",
     *                      "spt_version_constraint": "^3.8.0",
     *                      "downloads": 892,
     *                      "published_at": "2025-01-05T12:30:00.000000Z"
     *                  }
     *              ],
     *              "license": {
     *                  "id": 1,
     *                  "name": "MIT",
     *                  "short_name": "MIT"
     *              },
     *              "published_at": "2025-01-09T17:48:53.000000Z",
     *              "created_at": "2024-12-11T14:48:53.000000Z",
     *              "updated_at": "2025-04-10T13:50:00.000000Z"
     *          }
     *      ],
     *      "links": {
     *          "first": "https://forge.test/api/v0/mods?include=versions,license&page=1",
     *          "last": "https://forge.test/api/v0/mods?include=versions,license&page=1",
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
     *                  "url": "https://forge.test/api/v0/mods?include=versions,license&page=1",
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
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'name,slug,featured,created_at')]
    #[UrlParam('filter[id]', description: 'Filter by comma-separated Mod IDs.', required: false, example: '1,5,10')]
    #[UrlParam('filter[hub_id]', description: 'Filter by comma-separated Hub IDs.', required: false, example: '123,456')]
    #[UrlParam('filter[guid]', description: 'Filter by comma-separated GUIDs.', required: false, example: 'com.example.mymod1,com.example.mymod2')]
    #[UrlParam('filter[name]', description: 'Filter by name (fuzzy filter).', required: false, example: 'Raid Time')]
    #[UrlParam('filter[slug]', description: 'Filter by slug (fuzzy filter).', required: false, example: 'some-mod')]
    #[UrlParam('filter[teaser]', description: 'Filter by teaser text (fuzzy filter).', required: false, example: 'important')]
    #[UrlParam('filter[featured]', description: 'Filter by featured status (1, true, 0, false).', required: false, example: 'true')]
    #[UrlParam('filter[contains_ads]', type: 'boolean', description: 'Filter by contains_ads status (1, true, 0, false).', required: false, example: 'false')]
    #[UrlParam('filter[contains_ai_content]', type: 'boolean', description: 'Filter by contains_ai_content status (1, true, 0, false).', required: false, example: 'false')]
    #[UrlParam('filter[category_id]', description: 'Filter by comma-separated category IDs.', required: false, example: '1,2,3')]
    #[UrlParam('filter[category_slug]', description: 'Filter by comma-separated category slugs.', required: false, example: 'weapons,gear')]
    #[UrlParam('filter[created_between]', description: 'Filter by creation date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[updated_between]', description: 'Filter by update date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[published_between]', description: 'Filter by publication date range (YYYY-MM-DD,YYYY-MM-DD).', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[spt_version]', description: 'Filter mods that are compatible with an SPT version SemVer constraint. This will only filter the mods, not the mod versions.', required: false, example: '^3.8.0')]
    #[UrlParam('filter[fika_compatibility]', type: 'boolean', description: 'Filter by Fika compatibility status. When true, only shows mods with Fika compatible versions (1, true, 0, false).', required: false, example: 'true')]
    #[UrlParam('query', description: 'Search query to filter mods using Meilisearch. This will search across name, slug, and description fields.', required: false, example: 'raid time')]
    #[UrlParam('include', description: 'Comma-separated list of relationships. Available: `owner`, `additional_authors`, `versions`, `license`, `category`, `source_code_links`.', required: false, example: 'owner,versions')]
    #[UrlParam('sort', description: 'Sort results by attribute(s). Default ASC. Prefix with `-` for DESC. Comma-separate multiple fields. Allowed: `name`, `featured`, `created_at`, `updated_at`, `published_at`.', required: false, example: 'featured,-name')]
    #[UrlParam('page', type: 'integer', description: 'The page number for pagination.', required: false, example: 2)]
    #[UrlParam('per_page', type: 'integer', description: 'The number of results per page (max 50).', required: false, example: 25)]
    public function index(Request $request): JsonResponse
    {
        $queryBuilder = (new ModQueryBuilder)
            ->withFilters($request->input('filter'))
            ->withIncludes($request->string('include')->explode(',')->all())
            ->withFields($request->string('fields')->explode(',')->all())
            ->withSorts($request->string('sort')->explode(',')->all())
            ->withSearch($request->string('query')->toString());

        $mods = $queryBuilder->paginate($request->integer('per_page', 12));

        return ApiResponse::success(ModResource::collection($mods));
    }

    /**
     * Get Mod Details
     *
     * Retrieves details for a single mod, allowing relationship inclusion.
     *
     * Fields available:<br /><code>hub_id, guid, name, slug, teaser, description, thumbnail, downloads,
     * detail_url, fika_compatibility, featured, contains_ai_content, contains_ads, published_at, created_at,
     * updated_at</code>
     *
     * <aside class="notice">This endpoint only offers limited version information. Only the latest 6 versions will be
     * included. For additional version information, use the <code>mod/{id}/versions</code> endpoint.</aside>
     *
     * <aside class="notice">
     * The <code>fika_compatibility</code> field on a mod is a boolean that indicates whether any published version is
     * confirmed compatible with Fika. Version records expose their own <code>fika_compatibility</code> field as a
     * string with one of <code>compatible</code>, <code>incompatible</code>, or <code>unknown</code>.
     * </aside>
     *
     * @response status=200 scenario="Success (All fields, No Includes)"
     *  {
     *      "success": true,
     *      "data": {
     *          "id": 2,
     *          "hub_id": null,
     *          "guid": "com.baumbach.adipisci-iusto-voluptas-nihil",
     *          "name": "Adipisci iusto voluptas nihil.",
     *          "slug": "adipisci-iusto-voluptas-nihil",
     *          "teaser": "Minima adipisci perspiciatis nemo maiores rem porro natus.",
     *          "thumbnail": "",
     *          "downloads": 219598104,
     *          "description": "Adipisci rerum minima maiores sed. Neque totam quia libero exercitationem ullam.",
     *          "source_code_links": [
     *              {
     *                  "url": "http://baumbach.net/",
     *                  "label": null
     *              }
     *          ],
     *          "detail_url": "https://forge.sp-tarkov.com/mods/2/adipisci-iusto-voluptas-nihil",
     *          "fika_compatibility": true,
     *          "featured": false,
     *          "contains_ads": true,
     *          "contains_ai_content": true,
     *          "published_at": "2024-08-30T14:48:53.000000Z",
     *          "created_at": "2024-06-22T04:48:53.000000Z",
     *          "updated_at": "2025-04-10T13:50:21.000000Z"
     *      }
     *  }
     * @response status=200 scenario="Success (Include Authors and License)"
     *  {
     *      "success": true,
     *      "data": {
     *          "id": 2,
     *          "hub_id": null,
     *          "guid": "com.baumbach.adipisci-iusto-voluptas-nihil",
     *          "name": "Adipisci iusto voluptas nihil.",
     *          "slug": "adipisci-iusto-voluptas-nihil",
     *          "teaser": "Minima adipisci perspiciatis nemo maiores rem porro natus.",
     *          "thumbnail": "",
     *          "downloads": 219598104,
     *          "description": "Adipisci rerum minima maiores sed. Neque totam quia libero exercitationem ullam.",
     *          "source_code_links": [
     *              {
     *                  "url": "http://baumbach.net/",
     *                  "label": null
     *              }
     *          ],
     *          "detail_url": "https://forge.sp-tarkov.com/mods/2/adipisci-iusto-voluptas-nihil",
     *          "fika_compatibility": true,
     *          "featured": false,
     *          "contains_ads": true,
     *          "contains_ai_content": true,
     *          "additional_authors": [
     *              {
     *                  "id": 5,
     *                  "name": "ContributorOne",
     *                  "profile_photo_url": "https://example.com/contributor1.jpg",
     *                  "cover_photo_url": "https://example.com/cover1.jpg"
     *              },
     *              {
     *                  "id": 8,
     *                  "name": "ContributorTwo",
     *                  "profile_photo_url": "https://example.com/contributor2.jpg",
     *                  "cover_photo_url": "https://example.com/cover2.jpg"
     *              }
     *          ],
     *          "license": {
     *              "id": 2,
     *              "name": "GNU General Public License v3.0",
     *              "short_name": "GPL-3.0"
     *          },
     *          "published_at": "2024-08-30T14:48:53.000000Z",
     *          "created_at": "2024-06-22T04:48:53.000000Z",
     *          "updated_at": "2025-04-10T13:50:21.000000Z"
     *      }
     *  }
     * @response status=200 scenario="Success (Include All Available Relationships)"
     *  {
     *      "success": true,
     *      "data": {
     *          "id": 2,
     *          "hub_id": null,
     *          "guid": "com.baumbach.adipisci-iusto-voluptas-nihil",
     *          "name": "Adipisci iusto voluptas nihil.",
     *          "slug": "adipisci-iusto-voluptas-nihil",
     *          "teaser": "Minima adipisci perspiciatis nemo maiores rem porro natus.",
     *          "thumbnail": "",
     *          "downloads": 219598104,
     *          "description": "Adipisci rerum minima maiores sed. Neque totam quia libero exercitationem ullam.",
     *          "source_code_links": [
     *              {
     *                  "url": "http://baumbach.net/",
     *                  "label": null
     *              }
     *          ],
     *          "detail_url": "https://forge.sp-tarkov.com/mods/2/adipisci-iusto-voluptas-nihil",
     *          "fika_compatibility": true,
     *          "featured": false,
     *          "contains_ads": true,
     *          "contains_ai_content": true,
     *          "owner": {
     *              "id": 1,
     *              "name": "ModOwner",
     *              "profile_photo_url": "https://example.com/owner.jpg",
     *              "cover_photo_url": "https://example.com/owner-cover.jpg"
     *          },
     *          "additional_authors": [
     *              {
     *                  "id": 5,
     *                  "name": "ContributorOne",
     *                  "profile_photo_url": "https://example.com/contributor1.jpg",
     *                  "cover_photo_url": "https://example.com/cover1.jpg"
     *              }
     *          ],
     *          "versions": [
     *              {
     *                  "id": 45,
     *                  "version": "2.1.0",
     *                  "spt_version_constraint": "^3.9.0",
     *                  "downloads": 5234,
     *                  "published_at": "2025-02-15T10:30:00.000000Z"
     *              },
     *              {
     *                  "id": 44,
     *                  "version": "2.0.5",
     *                  "spt_version_constraint": "^3.8.0",
     *                  "downloads": 12456,
     *                  "published_at": "2025-01-20T08:15:00.000000Z"
     *              }
     *          ],
     *          "license": {
     *              "id": 2,
     *              "name": "GNU General Public License v3.0",
     *              "short_name": "GPL-3.0"
     *          },
     *          "category": {
     *              "id": 3,
     *              "name": "Quality of Life",
     *              "slug": "quality-of-life",
     *              "color_class": "purple"
     *          },
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
    #[UrlParam('include', description: 'Comma-separated list of relationships. Available: `owner`, `additional_authors`, `versions`, `license`, `category`, `source_code_links`.', required: false, example: 'owner,versions')]
    public function show(Request $request, int $modId): JsonResponse
    {
        $queryBuilder = (new ModQueryBuilder)
            ->withIncludes($request->string('include')->explode(',')->all())
            ->withFields($request->string('fields')->explode(',')->all());

        try {
            $mod = $queryBuilder->findOrFail($modId);
        } catch (ModelNotFoundException) {
            // If mod is not found with public filters, return 404
            return ApiResponse::error(
                'Resource not found.',
                Response::HTTP_NOT_FOUND,
                ApiErrorCode::NOT_FOUND
            );
        }

        return ApiResponse::success(new ModResource($mod));
    }
}
