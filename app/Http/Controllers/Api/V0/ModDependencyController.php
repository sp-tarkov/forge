<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\ModResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Services\DependencyService;
use App\Support\VersionMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
     * Resolves the complete dependency tree for one or more mod versions, returning all required dependencies recursively.
     * This endpoint is designed for mod managers and installers that need to determine which mods must be downloaded and
     * installed to satisfy all dependencies for a given set of mods.
     *
     * **How it works:**
     * - Accepts one or more `identifier:version` pairs where identifier can be either a mod_id (numeric) or GUID (string) (e.g., `5:1.2.0,com.example.mod:2.0.5`)
     * - For each queried mod version, resolves all direct and transitive dependencies
     * - Returns a flattened tree structure with each dependency and its nested dependencies
     * - Applies intelligent deduplication when multiple queried mods depend on the same mod
     * - Detects and flags version constraint conflicts
     *
     * **Smart Deduplication:**
     * When multiple queried mods share the same dependency, the endpoint analyzes semantic version constraints:
     * - **Compatible constraints** (e.g., ^1.0.0 and ^1.5.0): Returns only the highest version satisfying all constraints (e.g., 1.8.0), with `conflict: false`
     * - **Incompatible constraints** (e.g., ^1.0.0 and ^2.0.0): Returns all conflicting versions, each marked with `conflict: true`
     *
     * **Response Structure:**
     * Each mod in the response includes:
     * - **Mod fields**: `id`, `guid`, `name`, `slug` - Essential identifying information
     * - **latest_compatible_version**: The highest version satisfying all constraints, containing:
     *   - `id` - Mod version ID
     *   - `version` - Semantic version string
     *   - `link` - Download URL for the mod file
     *   - `content_length` - File size in bytes
     *   - `fika_compatibility` - Compatibility status with Fika mod
     * - **conflict**: Boolean indicating if this dependency has incompatible version constraints
     * - **dependencies**: Array of nested dependencies (same structure, recursive)
     *
     * @response status=200 scenario="Success (Compatible Dependencies)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 5,
     *              "guid": "com.example.dependency",
     *              "name": "Dependency Mod",
     *              "slug": "dependency-mod",
     *              "latest_compatible_version": {
     *                  "id": 42,
     *                  "version": "2.1.0",
     *                  "link": "https://forge.sp-tarkov.com/mod/download/5/dependency-mod/2.1.0",
     *                  "content_length": 1048576,
     *                  "fika_compatibility": "compatible"
     *              },
     *              "conflict": false,
     *              "dependencies": [
     *                  {
     *                      "id": 8,
     *                      "guid": "com.example.subdep",
     *                      "name": "Sub Dependency",
     *                      "slug": "sub-dependency",
     *                      "latest_compatible_version": {
     *                          "id": 67,
     *                          "version": "1.0.0",
     *                          "link": "https://forge.sp-tarkov.com/mod/download/8/sub-dependency/1.0.0",
     *                          "content_length": 524288,
     *                          "fika_compatibility": "unknown"
     *                      },
     *                      "conflict": false,
     *                      "dependencies": []
     *                  }
     *              ]
     *          }
     *      ]
     *  }
     * @response status=200 scenario="Success (Conflicting Dependencies)"
     *  {
     *      "success": true,
     *      "data": [
     *          {
     *              "id": 12,
     *              "guid": "com.example.conflicting",
     *              "name": "Conflicting Dependency",
     *              "slug": "conflicting-dependency",
     *              "latest_compatible_version": {
     *                  "id": 100,
     *                  "version": "1.5.0",
     *                  "link": "https://forge.sp-tarkov.com/mod/download/12/conflicting-dependency/1.5.0",
     *                  "content_length": 2097152,
     *                  "fika_compatibility": "compatible"
     *              },
     *              "conflict": true,
     *              "dependencies": []
     *          },
     *          {
     *              "id": 12,
     *              "guid": "com.example.conflicting",
     *              "name": "Conflicting Dependency",
     *              "slug": "conflicting-dependency",
     *              "latest_compatible_version": {
     *                  "id": 150,
     *                  "version": "2.0.0",
     *                  "link": "https://forge.sp-tarkov.com/mod/download/12/conflicting-dependency/2.0.0",
     *                  "content_length": 3145728,
     *                  "fika_compatibility": "incompatible"
     *              },
     *              "conflict": true,
     *              "dependencies": []
     *          }
     *      ]
     *  }
     * @response status=200 scenario="Success (No Dependencies Found)"
     *  {
     *      "success": true,
     *      "data": []
     *  }
     * @response status=400 scenario="Missing Parameter"
     *  {
     *      "success": false,
     *      "code": "VALIDATION_FAILED",
     *      "message": "You must provide the 'mods' parameter."
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
    public function resolve(Request $request): JsonResponse
    {
        // Reject array input (e.g. mods[]=x) before string casting, which would otherwise raise an
        // "Array to string conversion" error and surface as a 500.
        if (is_array($request->input('mods'))) {
            return ApiResponse::error(
                "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either a mod_id (numeric) or GUID (string)",
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        $modsParam = $request->string('mods')->trim()->toString();

        // Validate that the parameter is provided
        if (empty($modsParam)) {
            return ApiResponse::error(
                "You must provide the 'mods' parameter.",
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        // Parse identifier:version pairs using service
        $modVersionPairs = $this->dependencyService->parseModVersionPairs($modsParam);

        if ($modVersionPairs->isEmpty()) {
            return ApiResponse::error(
                "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either a mod_id (numeric) or GUID (string)",
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        // Look up mod version IDs from identifier:version pairs using service
        $queriedModVersionIds = $this->dependencyService->resolveModVersionIds($modVersionPairs);

        if ($queriedModVersionIds->isEmpty()) {
            return ApiResponse::success([]);
        }

        // Build dependency trees for each queried mod version, including constraint information
        /** @var Collection<int, ModResource> $allDependencies */
        $allDependencies = collect();
        /** @var Collection<int, Collection<int, string>> $constraintsByModId */
        $constraintsByModId = collect();

        foreach ($queriedModVersionIds as $versionId) {
            /** @var Collection<int, int> $processedVersionIds */
            $processedVersionIds = collect();
            $dependencies = $this->buildDependencyTree($versionId, $processedVersionIds, $constraintsByModId);
            if ($dependencies) {
                $allDependencies = $allDependencies->merge($dependencies);
            }
        }

        // Smart deduplication: group by mod ID and handle version conflicts
        $uniqueDependencies = $this->deduplicateDependencies($allDependencies, $constraintsByModId);

        return ApiResponse::success($uniqueDependencies);
    }

    /**
     * Deduplicate dependencies intelligently by checking version constraints.
     *
     * @param  Collection<int, ModResource>  $dependencies
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId
     * @return array<int, ModResource>
     */
    private function deduplicateDependencies(Collection $dependencies, Collection $constraintsByModId): array
    {
        return $dependencies
            ->groupBy(fn (ModResource $resource) => $resource->resource->id)
            ->flatMap(function (Collection $modVersions, int $modId) use ($constraintsByModId) {
                // If there's only one version of this mod, keep it (no conflict)
                if ($modVersions->count() === 1) {
                    /** @var ModResource $first */
                    $first = $modVersions->first();
                    $first->resource->conflict = false;

                    return $modVersions;
                }

                // Get all constraints for this mod from all queried mod versions
                $constraints = $constraintsByModId->get($modId, collect());

                if ($constraints->isEmpty()) {
                    // No constraints available, keep first occurrence (no conflict)
                    $resource = $modVersions->take(1)->first();
                    if ($resource) {
                        $resource->resource->conflict = false;
                    }

                    return $modVersions->take(1);
                }

                // Get all available versions for this mod
                $allVersions = $modVersions->pluck('resource.latestCompatibleVersion.version')->filter();

                // Find versions that satisfy ALL constraints
                $satisfyingVersions = $allVersions->filter(fn (mixed $version): bool => is_string($version) && $constraints->every(fn (string $constraint): bool => VersionMatcher::satisfies($version, $constraint)));

                if ($satisfyingVersions->isNotEmpty()) {
                    // Find the highest version that satisfies all constraints (no conflict)
                    /** @var array<string> $versionStrings */
                    $versionStrings = $satisfyingVersions->all();
                    $sortedVersions = VersionMatcher::rsort($versionStrings);
                    $highestSatisfyingVersion = $sortedVersions[0];

                    // Keep only the mod resource with this version
                    $filtered = $modVersions->filter(fn (ModResource $resource): bool => $resource->resource->latestCompatibleVersion?->version === $highestSatisfyingVersion)->take(1);

                    /** @var ModResource $firstFiltered */
                    $firstFiltered = $filtered->first();
                    $firstFiltered->resource->conflict = false;

                    return $filtered;
                }

                // No version satisfies all constraints - keep all versions to show the conflict
                $modVersions->each(fn (ModResource $resource): true => $resource->resource->conflict = true);

                return $modVersions;
            })
            ->values()
            ->all();
    }

    /**
     * Recursively build the dependency tree for a mod version.
     *
     * @param  Collection<int, int>  $processedVersionIds  Track visited versions to prevent infinite loops
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId  Collect constraints for each mod
     * @return array<int, ModResource>|null
     */
    private function buildDependencyTree(int $modVersionId, Collection $processedVersionIds, Collection $constraintsByModId): ?array
    {
        // Use service to build the dependency tree
        $tree = $this->dependencyService->buildDependencyTree($modVersionId, $processedVersionIds, $constraintsByModId);

        if (is_null($tree)) {
            return null;
        }

        if ($tree === []) {
            return [];
        }

        // Transform tree nodes into ModResources
        return collect($tree)->map(function (array $node) use ($constraintsByModId): ModResource {
            $mod = $node['mod'];
            $latestVersion = $node['latest_version'];
            /** @var array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}> $subDependencies */
            $subDependencies = $node['dependencies'];

            // Attach the latest compatible version and dependencies to the mod (dynamic properties for API response)
            $mod->latestCompatibleVersion = $latestVersion;

            // Apply smart deduplication to nested dependencies as well
            $deduplicatedSubDeps = $this->deduplicateDependencies(collect($this->transformTreeToResources($subDependencies, $constraintsByModId)), $constraintsByModId);
            $mod->dependencies = $deduplicatedSubDeps;

            return new ModResource($mod);
        })->values()->all();
    }

    /**
     * Transform dependency tree nodes into ModResources recursively.
     *
     * @param  array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}>  $tree
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId
     * @return array<int, ModResource>
     */
    private function transformTreeToResources(array $tree, Collection $constraintsByModId): array
    {
        return collect($tree)->map(function (array $node) use ($constraintsByModId): ModResource {
            $mod = $node['mod'];
            $latestVersion = $node['latest_version'];
            /** @var array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}> $subDependencies */
            $subDependencies = $node['dependencies'];

            // Attach the latest compatible version and dependencies to the mod
            $mod->latestCompatibleVersion = $latestVersion;

            // Recursively transform and deduplicate sub-dependencies
            $deduplicatedSubDeps = $this->deduplicateDependencies(
                collect($this->transformTreeToResources($subDependencies, $constraintsByModId)),
                $constraintsByModId
            );
            $mod->dependencies = $deduplicatedSubDeps;

            return new ModResource($mod);
        })->values()->all();
    }
}
