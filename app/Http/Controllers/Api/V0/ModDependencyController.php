<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V0\ModResource;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Models\Mod;
use App\Support\Api\V0\QueryBuilder\ModDependencyTreeQueryBuilder;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\UrlParam;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Mods
 *
 * Endpoints for resolving mod dependency trees.
 */
class ModDependencyController extends Controller
{
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
     *                  "link": "https://example.com/mods/dependency-mod-2.1.0.zip",
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
     *                          "link": "https://example.com/mods/sub-dependency-1.0.0.zip",
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
     *                  "link": "https://example.com/mods/conflicting-1.5.0.zip",
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
     *                  "link": "https://example.com/mods/conflicting-2.0.0.zip",
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
    #[UrlParam('mods', description: 'Comma-separated list of identifier:version pairs to resolve dependencies for. Identifier can be either a mod_id (numeric) or GUID (string). Version strings must match exactly.', required: true, example: '5:1.2.0,com.example.mod:2.0.5,15:3.1.0')]
    public function resolve(Request $request): JsonResponse
    {
        $modsParam = $request->string('mods')->trim()->toString();

        // Validate that the parameter is provided
        if (empty($modsParam)) {
            return ApiResponse::error(
                "You must provide the 'mods' parameter.",
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        // Parse identifier:version pairs
        $modVersionPairs = collect(explode(',', $modsParam))
            ->map(fn (string $pair): string => mb_trim($pair))
            ->reject(fn (string $pair): bool => empty($pair))
            ->unique()
            ->map(function (string $pair): ?array {
                $parts = explode(':', $pair);
                if (count($parts) !== 2) {
                    return null;
                }

                $identifier = mb_trim($parts[0]);
                $version = mb_trim($parts[1]);

                if (empty($identifier) || empty($version)) {
                    return null;
                }

                // Determine if identifier is numeric (mod_id) or string (GUID)
                $isNumeric = is_numeric($identifier) && (int) $identifier > 0;

                return [
                    'identifier' => $identifier,
                    'version' => $version,
                    'is_mod_id' => $isNumeric,
                ];
            })
            ->filter()
            ->values();

        if ($modVersionPairs->isEmpty()) {
            return ApiResponse::error(
                "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version' where identifier is either a mod_id (numeric) or GUID (string)",
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        // Look up mod version IDs from identifier:version pairs
        $queriedModVersionIds = collect();
        foreach ($modVersionPairs as $pair) {
            $query = DB::table('mod_versions')
                ->join('mods', 'mod_versions.mod_id', '=', 'mods.id')
                ->where('mod_versions.version', $pair['version'])
                ->whereNotNull('mod_versions.published_at')
                ->where('mod_versions.published_at', '<=', now())
                ->where('mod_versions.disabled', false)
                ->whereNotNull('mods.published_at')
                ->where('mods.published_at', '<=', now())
                ->where('mods.disabled', false);

            if ($pair['is_mod_id']) {
                $query->where('mods.id', (int) $pair['identifier']);
            } else {
                $query->where('mods.guid', $pair['identifier']);
            }

            $versionId = $query->value('mod_versions.id');

            if ($versionId) {
                $queriedModVersionIds->push($versionId);
            }
        }

        if ($queriedModVersionIds->isEmpty()) {
            return ApiResponse::success([]);
        }

        // Build dependency trees for each queried mod version, including constraint information
        $allDependencies = collect();
        $constraintsByModId = collect();

        foreach ($queriedModVersionIds as $versionId) {
            $dependencies = $this->buildDependencyTree($versionId, collect(), $request, $constraintsByModId);
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
                    $modVersions->first()->resource->conflict = false;

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
                $satisfyingVersions = $allVersions->filter(fn (string $version) => $constraints->every(fn (string $constraint) => Semver::satisfies($version, $constraint)));

                if ($satisfyingVersions->isNotEmpty()) {
                    // Find the highest version that satisfies all constraints (no conflict)
                    $sortedVersions = Semver::rsort($satisfyingVersions->all());
                    $highestSatisfyingVersion = $sortedVersions[0];

                    // Keep only the mod resource with this version
                    $filtered = $modVersions->filter(fn (ModResource $resource): bool => $resource->resource->latestCompatibleVersion?->version === $highestSatisfyingVersion)->take(1);

                    $filtered->first()->resource->conflict = false;

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
    private function buildDependencyTree(int $modVersionId, Collection $processedVersionIds, Request $request, Collection $constraintsByModId): ?array
    {
        // Check for circular dependencies
        if ($processedVersionIds->contains($modVersionId)) {
            return null;
        }

        // Mark this version as processed
        $processedVersionIds = $processedVersionIds->push($modVersionId);

        // Get the latest resolved version for each dependency by semantic version
        $dependencies = DB::table('mod_resolved_dependencies')
            ->select(
                'mod_dependencies.dependent_mod_id',
                'mod_dependencies.constraint',
                DB::raw('MAX(resolved_versions.id) as latest_version_id')
            )
            ->join('mod_dependencies', 'mod_resolved_dependencies.dependency_id', '=', 'mod_dependencies.id')
            ->join('mod_versions as resolved_versions', function (JoinClause $join): void {
                $join->on('mod_resolved_dependencies.resolved_mod_version_id', '=', 'resolved_versions.id')
                    ->whereNotNull('resolved_versions.published_at')
                    ->where('resolved_versions.published_at', '<=', now())
                    ->where('resolved_versions.disabled', false);
            })
            ->join('mods', function (JoinClause $join): void {
                $join->on('mod_dependencies.dependent_mod_id', '=', 'mods.id')
                    ->whereNotNull('mods.published_at')
                    ->where('mods.published_at', '<=', now())
                    ->where('mods.disabled', false);
            })
            ->join(DB::raw('(
                SELECT
                    mv.mod_id,
                    mv.id,
                    ROW_NUMBER() OVER (
                        PARTITION BY mv.mod_id
                        ORDER BY mv.version_major DESC, mv.version_minor DESC, mv.version_patch DESC,
                                 CASE WHEN mv.version_labels = "" THEN 0 ELSE 1 END, mv.version_labels
                    ) as rn
                FROM mod_versions mv
                INNER JOIN mod_resolved_dependencies mrd ON mv.id = mrd.resolved_mod_version_id
                WHERE mrd.mod_version_id = '.$modVersionId.'
                    AND mv.published_at IS NOT NULL
                    AND mv.published_at <= NOW()
                    AND mv.disabled = 0
            ) as ranked'), function (JoinClause $join): void {
                $join->on('resolved_versions.id', '=', 'ranked.id')
                    ->where('ranked.rn', '=', 1);
            })
            ->where('mod_resolved_dependencies.mod_version_id', $modVersionId)
            ->groupBy('mod_dependencies.dependent_mod_id', 'mod_dependencies.constraint')
            ->get();

        if ($dependencies->isEmpty()) {
            return [];
        }

        // Store constraints for each mod
        foreach ($dependencies as $dependency) {
            $modId = $dependency->dependent_mod_id;
            if (! $constraintsByModId->has($modId)) {
                $constraintsByModId->put($modId, collect());
            }

            $constraintsByModId->get($modId)->push($dependency->constraint);
        }

        // Load the actual mod versions with their mods using QueryBuilder for consistency
        $versionIds = $dependencies->pluck('latest_version_id')->filter()->unique()->all();

        // Get the mods for these versions
        $queryBuilder = new ModDependencyTreeQueryBuilder;

        // Get all mods that have a version in our version IDs list
        $mods = $queryBuilder->apply()
            ->whereHas('versions', function (Builder $query) use ($versionIds): void {
                $query->whereIn('id', $versionIds);
            })
            ->with(['versions' => function (mixed $query) use ($versionIds): void {
                $query->whereIn('id', $versionIds);
            }])
            ->get();

        // Build a map of mod_id => latest_version_id from our dependencies
        $modVersionMap = $dependencies->pluck('latest_version_id', 'dependent_mod_id');

        // Build tree nodes for each mod
        return $mods->map(function (Mod $mod) use ($modVersionMap, $processedVersionIds, $request, $constraintsByModId): ModResource {
            $latestVersionId = $modVersionMap[$mod->id] ?? null;
            $latestVersion = $latestVersionId ? $mod->versions->firstWhere('id', $latestVersionId) : null;

            // Recursively build dependencies for this version
            $subDependencies = $latestVersionId
                ? $this->buildDependencyTree($latestVersionId, $processedVersionIds, $request, $constraintsByModId)
                : [];

            // Attach the latest compatible version and dependencies to the mod (dynamic properties for API response)
            $mod->latestCompatibleVersion = $latestVersion;

            // Apply smart deduplication to nested dependencies as well
            $deduplicatedSubDeps = $this->deduplicateDependencies(collect($subDependencies ?? []), $constraintsByModId);
            $mod->dependencies = $deduplicatedSubDeps;

            return new ModResource($mod);
        })->values()->all();
    }
}
