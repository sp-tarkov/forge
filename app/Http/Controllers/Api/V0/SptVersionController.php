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
 * @group SPT Versions
 *
 * Endpoints for retrieving SPT-related data.
 */
class SptVersionController extends Controller
{
    /**
     * Get SPT Versions
     *
     * Retrieves a paginated list of SPT versions, allowing filtering and sorting.
     *
     * Fields available:<br /><code>id, version, version_major, version_minor, version_patch, version_labels, mod_count,
     * link, color_class, created_at, updated_at</code>
     *
     * @response status=200 scenario="Success"
     * {
     *     "success": true,
     *     "data": [
     *         {
     *             "id": 2,
     *             "version": "3.11.3",
     *             "version_major": 3,
     *             "version_minor": 11,
     *             "version_patch": 3,
     *             "version_labels": "",
     *             "mod_count": 371,
     *             "link": "https://github.com/sp-tarkov/build/releases/tag/3.11.3",
     *             "color_class": "green",
     *             "created_at": "2025-04-08T19:29:40.000000Z",
     *             "updated_at": "2025-04-08T19:29:40.000000Z"
     *         },
     *         {
     *             "id": 3,
     *             "version": "3.11.2",
     *             "version_major": 3,
     *             "version_minor": 11,
     *             "version_patch": 2,
     *             "version_labels": "",
     *             "mod_count": 371,
     *             "link": "https://github.com/sp-tarkov/build/releases/tag/3.11.2",
     *             "color_class": "green",
     *             "created_at": "2025-03-31T12:39:00.000000Z",
     *             "updated_at": "2025-03-31T12:39:00.000000Z"
     *         }
     *     ],
     *     "links": {
     *         "first": "https://forge.sp-tarkov.com/api/v0/spt/versions?page=1",
     *         "last": "https://forge.sp-tarkov.com/api/v0/spt/versions?page=1",
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
     *                 "url": "https://forge.sp-tarkov.com/api/v0/spt/versions?page=1",
     *                 "label": "1",
     *                 "active": true
     *             },
     *             {
     *                 "url": null,
     *                 "label": "Next &raquo;",
     *                 "active": false
     *             }
     *         ],
     *         "path": "https://forge.sp-tarkov.com/api/v0/spt/versions",
     *         "per_page": 12,
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
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'id,version,created_at')]
    #[UrlParam('filter[id]', description: 'Filter by SPT version ID. Comma-separated.', required: false, example: '234,432')]
    #[UrlParam('filter[created_between]', description: 'Filter between by two created_at dates.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[updated_between]', description: 'Filter between by two updated_at dates.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[spt_version]', description: 'Filter versions that are compatible with a SemVer constraint.', required: false, example: '^3.9.0')]
    #[UrlParam('sort', description: 'Sort results by attribute(s). Default ASC. Prefix with `-` for DESC. Comma-separate multiple fields.', required: false, example: '-version,created_at')]
    #[UrlParam('page', type: 'integer', description: 'The page number for pagination.', required: false, example: 2)]
    #[UrlParam('per_page', type: 'integer', description: 'The number of results per page (max 50).', required: false, example: 25)]
    public function index(Request $request): JsonResponse
    {
        $queryBuilder = (new SptVersionQueryBuilder)
            ->withFilters($request->input('filter'))
            ->withFields($request->string('fields')->explode(',')->all())
            ->withSorts($request->string('sort')->explode(',')->all());

        $sptVersions = $queryBuilder->paginate($request->integer('per_page', 12));

        return ApiResponse::success(SptVersionResource::collection($sptVersions));
    }
}
