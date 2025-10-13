<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\ModVersionResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Support\Api\V0\QueryBuilder\ModVersionQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * @group Mods
 */
class ModVersionController extends Controller
{
    /**
     * Get Mod Versions
     *
     * Retrieves a paginated list of mod versions, allowing filtering, sorting, and relationship inclusion.
     *
     * Fields available:<br /><code>hub_id, version, description, link, content_length, spt_version_constraint, virus_total_link,
     * downloads, published_at, created_at, updated_at</code>
     *
     * The <code>content_length</code> field contains the file size in bytes as determined by the Content-Length header
     * from the download link. This field may be null for versions created before file size validation was implemented.
     *
     * <aside class="notice">This endpoint only offers limited mod version dependency information. Only the immediate
     * dependencies will be included. If a dependency has dependencies of its own, they will not be included. To resolve
     * the full tree of dependencies, use the <code>mod/dependencies</code> endpoint.</aside>
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
     *              "link": "http://kautzer.com/enim-ut-quis-suscipit-dolores.html",
     *              "content_length": 52428800,
     *              "spt_version_constraint": "^1.0.0",
     *              "virus_total_link": "https://herman.net/accusantium-vitae-et-totam-deleniti-cupiditate-dolorem-non-sit.html",
     *              "downloads": 8,
     *              "published_at": "2024-05-09T10:49:41.000000Z",
     *              "created_at": "2024-12-19T04:49:41.000000Z",
     *              "updated_at": "2025-02-18T11:49:41.000000Z"
     *          },
     *          {
     *              "id": 660,
     *              "hub_id": null,
     *              "version": "8.2.8",
     *              "description": "Mollitia voluptatem quia et ex aut. Qui libero tempore ut. Suscipit a eius recusandae aut pariatur soluta necessitatibus.",
     *              "link": "http://lockman.net/",
     *              "spt_version_constraint": "<4.0.0",
     *              "virus_total_link": "https://www.blick.com/quis-reprehenderit-quis-quia-nobis-assumenda-eveniet-ipsa-qui",
     *              "downloads": 3332503,
     *              "published_at": "2024-07-03T05:49:25.000000Z",
     *              "created_at": "2024-10-06T23:49:25.000000Z",
     *              "updated_at": "2024-10-15T03:49:25.000000Z"
     *          },
     *          {
     *              "id": 2,
     *              "hub_id": null,
     *              "version": "6.5.2",
     *              "description": "Consequatur modi et labore ea neque id. Natus sapiente amet rerum quia in molestiae autem. Eligendi molestiae blanditiis voluptatem earum.",
     *              "link": "https://auer.com/ipsum-ratione-sint-eveniet-aut-porro-qui-in-odio.html",
     *              "spt_version_constraint": "<4.0.0",
     *              "virus_total_link": "http://baumbach.com/impedit-earum-corporis-sunt.html",
     *              "downloads": 40217550,
     *              "published_at": "2024-12-23T14:48:58.000000Z",
     *              "created_at": "2024-09-26T13:48:58.000000Z",
     *              "updated_at": "2025-03-21T01:48:58.000000Z"
     *          },
     *          {
     *              "id": 363,
     *              "hub_id": null,
     *              "version": "5.9.5",
     *              "description": "Aut ut inventore aut ex tempora a aspernatur asperiores. A laborum ullam ex rerum illo dolorem cupiditate. Veritatis id dolor qui quam et.",
     *              "link": "http://kreiger.com/ut-voluptas-doloremque-natus-dolorem-odit-facilis",
     *              "spt_version_constraint": "^1.0.0",
     *              "virus_total_link": "http://www.mayert.net/dignissimos-rem-id-nam-cumque",
     *              "downloads": 11236658,
     *              "published_at": "2025-03-18T23:49:12.000000Z",
     *              "created_at": "2024-09-04T16:49:12.000000Z",
     *              "updated_at": "2024-05-26T13:49:12.000000Z"
     *          },
     *          {
     *              "id": 1217,
     *              "hub_id": null,
     *              "version": "2.6.8",
     *              "description": "Aut in rerum est labore omnis. Voluptatem est velit doloribus expedita et. Illo error ut aspernatur quia quo repellat tenetur.",
     *              "link": "http://www.becker.org/eum-laboriosam-ut-voluptates-voluptatibus-voluptates-nihil",
     *              "spt_version_constraint": ">=3.0.0",
     *              "virus_total_link": "http://www.dickens.org/omnis-dolor-et-culpa-illo-excepturi-beatae-optio",
     *              "downloads": 425925,
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
     * @response status=401 scenario="Unauthenticated"
     *  {
     *      "success": false,
     *      "code": "UNAUTHENTICATED",
     *      "message": "Unauthenticated."
     *  }
     */
    #[UrlParam('fields', description: 'Comma-separated list of fields to include in the response. Defaults to all fields.', required: false, example: 'id,version,link,created_at')]
    #[UrlParam('filter[id]', description: 'Filter by mod version ID. Comma-separated.', required: false, example: '234,432')]
    #[UrlParam('filter[hub_id]', description: 'Filter by mod hub ID. Comma-separated.', required: false, example: '234,432')]
    #[UrlParam('filter[version]', description: 'Filter mod versions by using a SemVer constraint.', required: false, example: '^1.0.0')]
    #[UrlParam('filter[description]', description: 'Fuzzy-filter by mod version description.', required: false, example: 'This is a description')]
    #[UrlParam('filter[link]', description: 'Filter by mod version link.', required: false, example: 'example.com')]
    #[UrlParam('filter[virus_total_link]', description: 'Filter by mod version virus total link.', required: false, example: 'example.com')]
    #[UrlParam('filter[published_between]', description: 'Filter by mod version published between.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[created_between]', description: 'Filter by mod version created between.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[updated_between]', description: 'Filter by mod version updated between.', required: false, example: '2025-01-01,2025-03-31')]
    #[UrlParam('filter[spt_version]', description: 'Filter mod versions that are compatible with a SemVer constraint.', required: false, example: '^3.8.0')]
    #[UrlParam('include', description: 'Comma-separated list of relationships. Available: dependencies.', required: false, example: 'dependencies')]
    #[UrlParam('sort', description: 'Sort results by attribute(s). Default ASC. Prefix with `-` for DESC. Comma-separate multiple fields.', required: false, example: '-version,-created_at')]
    #[UrlParam('page', type: 'integer', description: 'The page number for pagination.', required: false, example: 2)]
    #[UrlParam('per_page', type: 'integer', description: 'The number of results per page (max 50).', required: false, example: 25)]
    public function index(Request $request, int $modId): JsonResponse
    {
        $queryBuilder = new ModVersionQueryBuilder($modId)
            ->withFilters($request->input('filter'))
            ->withIncludes($request->string('include')->explode(',')->all())
            ->withFields($request->string('fields')->explode(',')->all())
            ->withSorts($request->string('sort')->explode(',')->all());

        $modVersions = $queryBuilder->paginate($request->integer('per_page', 12));

        return ApiResponse::success(ModVersionResource::collection($modVersions));
    }
}
