<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\AddonVersionResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Support\Api\V0\QueryBuilder\AddonVersionQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * @group Addons
 */
class AddonVersionController extends Controller
{
    /**
     * Get Addon Versions
     *
     * Retrieves a paginated list of addon versions, allowing filtering, sorting, and relationship inclusion.
     *
     * Fields available:<br /><code>id, version, description, link, content_length, mod_version_constraint,
     * downloads, published_at, created_at, updated_at</code>
     *
     * The <code>content_length</code> field contains the file size in bytes as determined by the Content-Length header
     * from the download link. This field may be null for versions created before file size validation was implemented.
     *
     * @response status=200 scenario="Success (All fields, No Includes)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 1,
     *              "version": "1.2.0",
     *              "description": "Added 10 new tracks",
     *              "link": "https://example.com/download/v1.2.0.zip",
     *              "content_length": 52428800,
     *              "mod_version_constraint": "^2.0.0",
     *              "downloads": 523,
     *              "published_at": "2025-01-09T17:48:53.000000Z",
     *              "created_at": "2024-12-11T14:48:53.000000Z",
     *              "updated_at": "2025-04-10T13:50:00.000000Z"
     *          },
     *          {
     *              "id": 2,
     *              "version": "1.1.0",
     *              "description": "Fixed audio glitches",
     *              "link": "https://example.com/download/v1.1.0.zip",
     *              "content_length": 51200000,
     *              "mod_version_constraint": "^2.0.0",
     *              "downloads": 1000,
     *              "published_at": "2024-12-15T10:30:00.000000Z",
     *              "created_at": "2024-11-20T08:15:00.000000Z",
     *              "updated_at": "2025-01-05T12:45:00.000000Z"
     *          }
     *      ],
     *      "links": {
     *          "first": "https://forge.sp-tarkov.com/api/v0/addon/1/versions?page=1",
     *          "last": "https://forge.sp-tarkov.com/api/v0/addon/1/versions?page=1",
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
     *                  "url": "https://forge.sp-tarkov.com/api/v0/addon/1/versions?page=1",
     *                  "label": "1",
     *                  "active": true
     *              },
     *              {
     *                  "url": null,
     *                  "label": "Next &raquo;",
     *                  "active": false
     *              }
     *          ],
     *          "path": "https://forge.sp-tarkov.com/api/v0/addon/1/versions",
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
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'id,version,link,created_at')]
    #[UrlParam('filter[id]', description: 'Filter by addon version ID. Comma-separated.', required: false, example: '234,432')]
    #[UrlParam('filter[version]', description: 'Filter addon versions by using a SemVer constraint.', required: false, example: '^1.0.0')]
    #[UrlParam('filter[description]', description: 'Fuzzy-filter by addon version description.', required: false, example: 'This is a description')]
    #[UrlParam('filter[link]', description: 'Filter by addon version link.', required: false, example: 'example.com')]
    #[UrlParam('filter[published_between]', description: 'Filter by addon version published between.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[created_between]', description: 'Filter by addon version created between.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[updated_between]', description: 'Filter by addon version updated between.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('include', description: 'Comma-separated list of relationships. Available: virus_total_links.', required: false, example: 'virus_total_links')]
    #[UrlParam('sort', description: 'Sort results by attribute(s). Default ASC. Prefix with `-` for DESC. Comma-separate multiple fields.', required: false, example: '-version,-created_at')]
    #[UrlParam('page', type: 'integer', description: 'The page number for pagination.', required: false, example: 2)]
    #[UrlParam('per_page', type: 'integer', description: 'The number of results per page (max 50).', required: false, example: 25)]
    public function index(Request $request, int $addonId): JsonResponse
    {
        $queryBuilder = new AddonVersionQueryBuilder($addonId)
            ->withFilters($request->input('filter'))
            ->withIncludes($request->string('include')->explode(',')->all())
            ->withFields($request->string('fields')->explode(',')->all())
            ->withSorts($request->string('sort')->explode(',')->all());

        $addonVersions = $queryBuilder->paginate($request->integer('per_page', 12));

        return ApiResponse::success(AddonVersionResource::collection($addonVersions));
    }
}
