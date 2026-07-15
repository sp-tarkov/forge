<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\ModVersionResource;
use App\Http\Resources\Api\V0\VerificationFileTreeResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Support\Api\V0\QueryBuilder\ModVersionQueryBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\QueryParam;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Mods
 */
final class ModVersionController extends Controller
{
    /**
     * Get Mod Versions
     *
     * Retrieves a paginated list of mod versions, allowing filtering, sorting, and relationship inclusion.
     *
     * Fields available:<br /><code>hub_id, version, description, link, content_length, spt_version_constraint,
     * downloads, fika_compatibility, published_at, created_at, updated_at</code>
     *
     * The <code>content_length</code> field contains the file size in bytes as determined by the Content-Length header
     * from the download link. This field may be null for versions created before file size validation was implemented.
     *
     * <aside class="notice">This endpoint only offers limited mod version dependency information. Only the immediate
     * dependencies will be included. If a dependency has dependencies of its own, they will not be included. To resolve
     * the full tree of dependencies, use the <a href="#mods-GETapi-v0-mods-dependencies"><code>/mods/dependencies</code></a>
     * endpoint.</aside>
     *
     * <aside class="notice">
     * The <code>fika_compatibility</code> field on each version is a string with one of <code>compatible</code>,
     * <code>incompatible</code>, or <code>unknown</code>.
     * </aside>
     *
     * @response status=200 scenario="Success (All fields, No Includes)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 938,
     *              "hub_id": null,
     *              "version": "0.2.9",
     *              "description": "Magni eius ad temporibus similique accusamus assumenda aliquid. Quisquam placeat in necessitatibus ducimus quasi odit. Autem nulla ea minus itaque.",
     *              "link": "https://forge.sp-tarkov.com/mod/download/1/example-mod/0.2.9",
     *              "content_length": 52428800,
     *              "spt_version_constraint": "^1.0.0",
     *              "downloads": 8,
     *              "fika_compatibility": "unknown",
     *              "published_at": "2024-05-09T10:49:41.000000Z",
     *              "created_at": "2024-12-19T04:49:41.000000Z",
     *              "updated_at": "2025-02-18T11:49:41.000000Z"
     *          },
     *          {
     *              "id": 660,
     *              "hub_id": null,
     *              "version": "8.2.8",
     *              "description": "Mollitia voluptatem quia et ex aut. Qui libero tempore ut. Suscipit a eius recusandae aut pariatur soluta necessitatibus.",
     *              "link": "https://forge.sp-tarkov.com/mod/download/1/example-mod/8.2.8",
     *              "spt_version_constraint": "<4.0.0",
     *              "downloads": 3332503,
     *              "fika_compatibility": "compatible",
     *              "published_at": "2024-07-03T05:49:25.000000Z",
     *              "created_at": "2024-10-06T23:49:25.000000Z",
     *              "updated_at": "2024-10-15T03:49:25.000000Z"
     *          },
     *          {
     *              "id": 2,
     *              "hub_id": null,
     *              "version": "6.5.2",
     *              "description": "Consequatur modi et labore ea neque id. Natus sapiente amet rerum quia in molestiae autem. Eligendi molestiae blanditiis voluptatem earum.",
     *              "link": "https://forge.sp-tarkov.com/mod/download/1/example-mod/6.5.2",
     *              "spt_version_constraint": "<4.0.0",
     *              "downloads": 40217550,
     *              "fika_compatibility": "incompatible",
     *              "published_at": "2024-12-23T14:48:58.000000Z",
     *              "created_at": "2024-09-26T13:48:58.000000Z",
     *              "updated_at": "2025-03-21T01:48:58.000000Z"
     *          },
     *          {
     *              "id": 363,
     *              "hub_id": null,
     *              "version": "5.9.5",
     *              "description": "Aut ut inventore aut ex tempora a aspernatur asperiores. A laborum ullam ex rerum illo dolorem cupiditate. Veritatis id dolor qui quam et.",
     *              "link": "https://forge.sp-tarkov.com/mod/download/1/example-mod/5.9.5",
     *              "spt_version_constraint": "^1.0.0",
     *              "downloads": 11236658,
     *              "fika_compatibility": "unknown",
     *              "published_at": "2025-03-18T23:49:12.000000Z",
     *              "created_at": "2024-09-04T16:49:12.000000Z",
     *              "updated_at": "2024-05-26T13:49:12.000000Z"
     *          },
     *          {
     *              "id": 1217,
     *              "hub_id": null,
     *              "version": "2.6.8",
     *              "description": "Aut in rerum est labore omnis. Voluptatem est velit doloribus expedita et. Illo error ut aspernatur quia quo repellat tenetur.",
     *              "link": "https://forge.sp-tarkov.com/mod/download/1/example-mod/2.6.8",
     *              "spt_version_constraint": ">=3.0.0",
     *              "downloads": 425925,
     *              "fika_compatibility": "compatible",
     *              "published_at": "2025-03-20T13:50:00.000000Z",
     *              "created_at": "2025-02-12T01:50:00.000000Z",
     *              "updated_at": "2025-03-17T07:50:00.000000Z"
     *          }
     *      ],
     *      "links": {
     *          "first": "https://forge.sp-tarkov.com/api/v0/mod/1/versions?page=1",
     *          "last": "https://forge.sp-tarkov.com/api/v0/mod/1/versions?page=1",
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
     *                  "url": "https://forge.sp-tarkov.com/api/v0/mod/1/versions?page=1",
     *                  "label": "1",
     *                  "active": true
     *              },
     *              {
     *                  "url": null,
     *                  "label": "Next &raquo;",
     *                  "active": false
     *              }
     *          ],
     *          "path": "https://forge.sp-tarkov.com/api/v0/mod/1/versions",
     *          "per_page": 12,
     *          "to": 5,
     *          "total": 5
     *      }
     *  }
     * @response status=200 scenario="Success (Include Dependencies)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 938,
     *              "hub_id": null,
     *              "version": "0.2.9",
     *              "description": "Magni eius ad temporibus similique accusamus assumenda aliquid. Quisquam placeat in necessitatibus ducimus quasi odit. Autem nulla ea minus itaque.",
     *              "link": "https://forge.sp-tarkov.com/mod/download/1/example-mod/0.2.9",
     *              "content_length": 52428800,
     *              "spt_version_constraint": "^1.0.0",
     *              "downloads": 8,
     *              "fika_compatibility": "unknown",
     *              "dependencies": [
     *                  {
     *                      "id": 5,
     *                      "mod_id": 42,
     *                      "mod_guid": "com.example.core-library",
     *                      "mod_name": "Core Library",
     *                      "version_constraint": "^2.0.0",
     *                      "is_optional": false
     *                  },
     *                  {
     *                      "id": 8,
     *                      "mod_id": 15,
     *                      "mod_guid": "com.example.helper-utils",
     *                      "mod_name": "Helper Utilities",
     *                      "version_constraint": ">=1.5.0",
     *                      "is_optional": true
     *                  }
     *              ],
     *              "published_at": "2024-05-09T10:49:41.000000Z",
     *              "created_at": "2024-12-19T04:49:41.000000Z",
     *              "updated_at": "2025-02-18T11:49:41.000000Z"
     *          },
     *          {
     *              "id": 660,
     *              "hub_id": null,
     *              "version": "8.2.8",
     *              "description": "Mollitia voluptatem quia et ex aut. Qui libero tempore ut. Suscipit a eius recusandae aut pariatur soluta necessitatibus.",
     *              "link": "https://forge.sp-tarkov.com/mod/download/1/example-mod/8.2.8",
     *              "spt_version_constraint": "<4.0.0",
     *              "downloads": 3332503,
     *              "fika_compatibility": "compatible",
     *              "dependencies": [],
     *              "published_at": "2024-07-03T05:49:25.000000Z",
     *              "created_at": "2024-10-06T23:49:25.000000Z",
     *              "updated_at": "2024-10-15T03:49:25.000000Z"
     *          }
     *      ],
     *      "links": {
     *          "first": "https://forge.sp-tarkov.com/api/v0/mod/1/versions?include=dependencies&page=1",
     *          "last": "https://forge.sp-tarkov.com/api/v0/mod/1/versions?include=dependencies&page=1",
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
     *                  "url": "https://forge.sp-tarkov.com/api/v0/mod/1/versions?include=dependencies&page=1",
     *                  "label": "1",
     *                  "active": true
     *              },
     *              {
     *                  "url": null,
     *                  "label": "Next &raquo;",
     *                  "active": false
     *              }
     *          ],
     *          "path": "https://forge.sp-tarkov.com/api/v0/mod/1/versions",
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
    #[QueryParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'id,version,link,created_at')]
    #[QueryParam('filter[id]', description: 'Filter by mod version ID. Comma-separated.', required: false, example: '234,432')]
    #[QueryParam('filter[hub_id]', description: 'Filter by mod hub ID. Comma-separated.', required: false, example: '234,432')]
    #[QueryParam('filter[version]', description: 'Filter mod versions by using a SemVer constraint.', required: false, example: '^1.0.0')]
    #[QueryParam('filter[description]', description: 'Fuzzy-filter by mod version description.', required: false, example: 'This is a description')]
    #[QueryParam('filter[published_between]', description: 'Filter by mod version published between.', required: false, example: '2025-01-01,2025-03-31')]
    #[QueryParam('filter[created_between]', description: 'Filter by mod version created between.', required: false, example: '2025-01-01,2025-03-31')]
    #[QueryParam('filter[updated_between]', description: 'Filter by mod version updated between.', required: false, example: '2025-01-01,2025-03-31')]
    #[QueryParam('filter[spt_version]', description: 'Filter mod versions that are compatible with a SemVer constraint.', required: false, example: '^3.8.0')]
    #[QueryParam('filter[fika_compatibility]', description: 'Filter by Fika compatibility status. Comma-separated. Available values: compatible, incompatible, unknown.', required: false, example: 'compatible')]
    #[QueryParam('include', description: 'Comma-separated list of relationships. Available: dependencies, virus_total_links.', required: false, example: 'dependencies,virus_total_links')]
    #[QueryParam('sort', description: 'Sort results by attribute(s). Default ASC. Prefix with `-` for DESC. Comma-separate multiple fields.', required: false, example: '-version,-created_at')]
    #[QueryParam('page', type: 'integer', description: 'The page number for pagination.', required: false, example: 2)]
    #[QueryParam('per_page', type: 'integer', description: 'The number of results per page (max 50).', required: false, example: 25)]
    public function index(Request $request, int $modId): JsonResponse
    {
        /** @var array<string, mixed>|null $filters */
        $filters = $request->input('filter');

        $queryBuilder = new ModVersionQueryBuilder($modId)
            ->withFilters($filters)
            ->withIncludes($request->string('include')->explode(',')->all())
            ->withFields($request->string('fields')->explode(',')->all())
            ->withSorts($request->string('sort')->explode(',')->all());

        $modVersions = $queryBuilder->paginate(min($request->integer('per_page', 12), 50));

        return ApiResponse::success(ModVersionResource::collection($modVersions));
    }

    /**
     * Get Mod Version File Tree
     *
     * Retrieves the archive file listing recorded by the latest passed verification of a mod version. The
     * <code>files</code> array contains the relative path of every file inside the version's download archive.
     *
     * <aside class="notice">A file tree is only available once a version has passed automated file verification. When
     * the version has no passed verification, or the mod or version is not publicly visible, a <code>NOT_FOUND</code>
     * response is returned. The <code>truncated</code> flag indicates that the archive contained more files than the
     * verification system records, so the listing is incomplete.</aside>
     *
     * @response status=200 scenario="Success"
     *  {
     *      "success": true,
     *      "data": {
     *          "verified_at": "2026-07-01T12:00:00.000000Z",
     *          "file_count": 3,
     *          "truncated": false,
     *          "files": [
     *              "BepInEx/plugins/ExampleMod.dll",
     *              "user/mods/example-mod/package.json",
     *              "user/mods/example-mod/src/mod.js"
     *          ]
     *      }
     *  }
     * @response status=404 scenario="File Tree Not Available"
     *  {
     *      "success": false,
     *      "code": "NOT_FOUND",
     *      "message": "Resource not found."
     *  }
     */
    public function fileTree(int $modId, int $versionId): JsonResponse
    {
        try {
            $modVersion = new ModVersionQueryBuilder($modId)->findOrFail($versionId);
        } catch (ModelNotFoundException) {
            return ApiResponse::error('Resource not found.', Response::HTTP_NOT_FOUND, ApiErrorCode::NOT_FOUND);
        }

        $verification = $modVersion->verificationResults()
            ->where('status', VerificationStatus::Passed)
            ->whereNotNull('file_tree')
            ->latest('id')
            ->first();

        if ($verification === null) {
            return ApiResponse::error('Resource not found.', Response::HTTP_NOT_FOUND, ApiErrorCode::NOT_FOUND);
        }

        return ApiResponse::success(new VerificationFileTreeResource($verification));
    }
}
