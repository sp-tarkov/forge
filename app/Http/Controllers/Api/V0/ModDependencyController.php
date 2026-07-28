<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Models\SptVersion;
use App\Services\DependencyService;
use App\Support\DataTransferObjects\DependencyTreeNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\QueryParam;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Mods
 *
 * Endpoints for resolving mod dependency trees.
 */
final class ModDependencyController extends Controller
{
    public function __construct(private readonly DependencyService $dependencyService) {}

    /**
     * Get Mod Dependencies
     *
     * Resolves the dependency tree of one or more mod versions for a specific SPT version. This endpoint is designed
     * for mod managers and installers that need to determine, in a single call, which mods must be downloaded and
     * installed for each queried mod and whether the full set of queried mods is internally consistent.
     *
     * **How it works:**
     * - Accepts one or more `identifier:version` pairs where identifier can be either a mod_id (numeric) or GUID (string) (e.g., `5:1.2.0,com.example.mod:2.0.5`)
     * - Requires an `spt_version`; every resolved dependency version is compatible with that SPT version
     * - Returns one dependency tree per queried pair, keyed by the pair exactly as it was provided
     * - Version selection is global across the whole request: when multiple queried mods share a dependency, every
     *   tree shows the same, highest version satisfying all of their constraints
     * - Detects and flags version constraint conflicts
     *
     * **Response Structure:**
     * `data` is an object with one key per queried `identifier:version` pair (exactly as provided, whitespace
     * trimmed). Pairs that do not resolve to a published mod version are omitted. A queried mod without dependencies
     * maps to an empty array. Each entry is an array of dependency nodes:
     * - **Mod fields**: `id`, `guid`, `name`, `slug` - Essential identifying information
     * - **latest_compatible_version**: The chosen version, containing:
     *   - `id` - Mod version ID
     *   - `version` - Semantic version string
     *   - `link` - Download URL for the mod file
     *   - `content_length` - File size in bytes
     *   - `fika_compatibility` - Compatibility status with Fika mod
     *   `null` when the dependency has no published version that is compatible with the given SPT version and
     *   satisfies the constraint; such nodes always have empty `dependencies`
     * - **conflict**: Boolean indicating that no single version of this dependency satisfies every queried mod's
     *   constraints; each tree then shows the version its own constraint resolves to
     * - **dependencies**: Array of nested dependencies (same structure, recursive)
     *
     * Version choices are made among the versions each constraint resolves to. When constraints are compatible, all
     * trees agree on one version per dependency mod, at every nesting level.
     *
     * @response status=200 scenario="Success"
     *  {
     *      "success": true,
     *      "data": {
     *          "com.example.mod:2.0.5": [
     *              {
     *                  "id": 5,
     *                  "guid": "com.example.dependency",
     *                  "name": "Dependency Mod",
     *                  "slug": "dependency-mod",
     *                  "latest_compatible_version": {
     *                      "id": 42,
     *                      "version": "2.1.0",
     *                      "link": "https://forge.sp-tarkov.com/mod/download/5/dependency-mod/2.1.0",
     *                      "content_length": 1048576,
     *                      "fika_compatibility": "compatible"
     *                  },
     *                  "conflict": false,
     *                  "dependencies": []
     *              }
     *          ],
     *          "15:3.1.0": []
     *      }
     *  }
     * @response status=200 scenario="Success (Unsatisfiable On This SPT Version)"
     *  {
     *      "success": true,
     *      "data": {
     *          "com.example.mod:2.0.5": [
     *              {
     *                  "id": 5,
     *                  "guid": "com.example.dependency",
     *                  "name": "Dependency Mod",
     *                  "slug": "dependency-mod",
     *                  "latest_compatible_version": null,
     *                  "conflict": false,
     *                  "dependencies": []
     *              }
     *          ]
     *      }
     *  }
     * @response status=200 scenario="Success (No Queried Mods Found)"
     *  {
     *      "success": true,
     *      "data": {}
     *  }
     * @response status=400 scenario="Missing Parameters"
     *  {
     *      "success": false,
     *      "code": "VALIDATION_FAILED",
     *      "message": "You must provide both 'mods' and 'spt_version' parameters."
     *  }
     * @response status=400 scenario="Unknown SPT Version"
     *  {
     *      "success": false,
     *      "code": "VALIDATION_FAILED",
     *      "message": "SPT version not found or not published."
     *  }
     * @response status=400 scenario="Invalid Format"
     *  {
     *      "success": false,
     *      "code": "VALIDATION_FAILED",
     *      "message": "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either a mod_id (numeric) or GUID (string)"
     *  }
     * @response status=401 scenario="Unauthenticated"
     *  {
     *      "success": false,
     *      "code": "UNAUTHENTICATED",
     *      "message": "Unauthenticated."
     *  }
     */
    #[QueryParam('mods', description: 'Comma-separated list of identifier:version pairs to resolve dependencies for. Identifier can be either a mod_id (numeric) or GUID (string). Version strings must match exactly.', required: true, example: '5:1.2.0,com.example.mod:2.0.5,15:3.1.0')]
    #[QueryParam('spt_version', description: 'SPT version to resolve dependency versions against. Must match a published SPT version exactly.', required: true, example: '3.11.5')]
    public function resolve(Request $request): JsonResponse
    {
        // Reject array input (e.g. mods[]=x) before string casting
        if (is_array($request->input('mods'))) {
            return ApiResponse::error(
                "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either a mod_id (numeric) or GUID (string)",
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        if (is_array($request->input('spt_version'))) {
            return ApiResponse::error(
                "You must provide both 'mods' and 'spt_version' parameters.",
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        $modsParam = $request->string('mods')->trim()->toString();
        $sptVersionParam = $request->string('spt_version')->trim()->toString();

        if (empty($modsParam) || empty($sptVersionParam)) {
            return ApiResponse::error(
                "You must provide both 'mods' and 'spt_version' parameters.",
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        $sptVersion = SptVersion::query()->where('version', $sptVersionParam)
            ->whereNotNull('publish_date')
            ->where('publish_date', '<=', now())
            ->first();

        if (! $sptVersion instanceof SptVersion) {
            return ApiResponse::error(
                'SPT version not found or not published.',
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        $modVersionPairs = $this->dependencyService->parseModVersionPairs($modsParam);

        if ($modVersionPairs->isEmpty()) {
            return ApiResponse::error(
                "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either a mod_id (numeric) or GUID (string)",
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        $queriedVersions = $this->dependencyService->resolveModVersions($modVersionPairs);

        if ($queriedVersions->isEmpty()) {
            return ApiResponse::success((object) []);
        }

        $groups = $this->dependencyService->resolveGroupedModDependencies($queriedVersions, $sptVersion->id);

        $data = array_map(
            fn (array $nodes): array => array_map(fn (DependencyTreeNode $node): array => $node->toArray(), $nodes),
            $groups
        );

        return ApiResponse::success($data === [] ? (object) [] : $data);
    }
}
