<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Responses\Api\V0\ApiResponse;
use App\Models\Dependency;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Services\DependencyService;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Knuckles\Scribe\Attributes\UrlParam;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Mods
 */
class ModUpdateController extends Controller
{
    public function __construct(protected DependencyService $dependencyService) {}

    /**
     * Get Mod Updates
     *
     * Checks for available updates for one or more installed mod versions, filtered by SPT version compatibility.
     * This endpoint intelligently handles dependency constraints and prerelease versions to provide safe update
     * recommendations.
     *
     * **How it works:**
     * - Accepts mod identifier:version pairs and a target SPT version
     * - Finds newer versions compatible with the target SPT version
     * - Validates that updates won't break dependencies
     * - Returns categorized results: safe updates, blocked updates, up-to-date mods, and incompatible mods
     *
     * **Prerelease Handling:**
     * - If a mod is on a prerelease version (e.g., 1.0.0-beta.1), the stable release (1.0.0) will be recommended if available, otherwise newer prereleases (e.g., 1.0.0-beta.2)
     * - If a mod is on a stable version, only stable versions are recommended (never prereleases)
     *
     * **Dependency Validation:**
     * - Checks if updating would break constraints from other installed mods
     * - Validates that all dependencies of the new version can be satisfied
     *
     * @response status=200 scenario="Success"
     *  {
     *      "success": true,
     *      "data": {
     *          "spt_version": "3.11.5",
     *          "updates": [
     *              {
     *                  "current_version": {
     *                      "id": 42,
     *                      "mod_id": 5,
     *                      "guid": "com.example.mod",
     *                      "name": "Example Mod",
     *                      "slug": "example-mod",
     *                      "version": "1.0.0"
     *                  },
     *                  "recommended_version": {
     *                      "id": 58,
     *                      "version": "1.5.0",
     *                      "link": "https://example.com/download",
     *                      "content_length": 1048576,
     *                      "fika_compatibility": "compatible",
     *                      "spt_versions": ["3.11.0", "3.11.5"]
     *                  },
     *                  "update_reason": "newer_version_available"
     *              }
     *          ],
     *          "blocked_updates": [
     *              {
     *                  "current_version": {
     *                      "id": 99,
     *                      "mod_id": 20,
     *                      "guid": "com.example.blocked",
     *                      "name": "Blocked Mod",
     *                      "version": "2.0.0"
     *                  },
     *                  "latest_version": {
     *                      "id": 105,
     *                      "version": "3.0.0",
     *                      "spt_versions": ["3.11.5"]
     *                  },
     *                  "block_reason": "dependency_constraint_violation",
     *                  "blocking_mods": [
     *                      {
     *                          "mod_id": 15,
     *                          "mod_guid": "com.example.dependent",
     *                          "mod_name": "Dependent Mod",
     *                          "current_version": "1.0.0",
     *                          "constraint": "^2.0.0",
     *                          "incompatible_with": "3.0.0"
     *                      }
     *                  ]
     *              }
     *          ],
     *          "up_to_date": [
     *              {
     *                  "id": 125,
     *                  "mod_id": 25,
     *                  "guid": "com.example.current",
     *                  "name": "Current Mod",
     *                  "version": "1.8.0",
     *                  "spt_versions": ["3.11.5"]
     *              }
     *          ],
     *          "incompatible_with_spt": [
     *              {
     *                  "id": 150,
     *                  "mod_id": 30,
     *                  "guid": "com.example.old",
     *                  "name": "Old Mod",
     *                  "version": "1.0.0",
     *                  "reason": "no_version_for_spt",
     *                  "latest_compatible_version": null
     *              }
     *          ]
     *      }
     *  }
     * @response status=400 scenario="Missing Parameter"
     *  {
     *      "success": false,
     *      "code": "VALIDATION_FAILED",
     *      "message": "You must provide both 'mods' and 'spt_version' parameters."
     *  }
     * @response status=400 scenario="Invalid SPT Version"
     *  {
     *      "success": false,
     *      "code": "VALIDATION_FAILED",
     *      "message": "SPT version not found or not published."
     *  }
     * @response status=401 scenario="Unauthenticated"
     *  {
     *      "success": false,
     *      "code": "UNAUTHENTICATED",
     *      "message": "Unauthenticated."
     *  }
     */
    #[UrlParam('mods', description: 'Comma-separated list of identifier:version pairs for installed mods. Identifier can be mod_id (numeric) or GUID (string).', required: true, example: '5:1.2.0,com.example.mod:2.0.5')]
    #[UrlParam('spt_version', description: 'Target SPT version to check compatibility against.', required: true, example: '3.11.5')]
    public function check(Request $request): JsonResponse
    {
        $modsParam = $request->string('mods')->trim()->toString();
        $sptVersionParam = $request->string('spt_version')->trim()->toString();

        // Validate parameters
        if (empty($modsParam) || empty($sptVersionParam)) {
            return ApiResponse::error(
                "You must provide both 'mods' and 'spt_version' parameters.",
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        // Parse mod version pairs
        $modVersionPairs = $this->dependencyService->parseModVersionPairs($modsParam);

        if ($modVersionPairs->isEmpty()) {
            return ApiResponse::error(
                "Invalid format for 'mods' parameter. Expected format: 'identifier:version,identifier:version'",
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        // Validate SPT version exists and is published
        $sptVersion = SptVersion::query()->where('version', $sptVersionParam)
            ->whereNotNull('publish_date')
            ->where('publish_date', '<=', now())
            ->first();

        if (! $sptVersion) {
            return ApiResponse::error(
                'SPT version not found or not published.',
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::VALIDATION_FAILED
            );
        }

        // Resolve mod version IDs for installed mods
        $installedModVersionIds = $this->dependencyService->resolveModVersionIds($modVersionPairs);

        if ($installedModVersionIds->isEmpty()) {
            return ApiResponse::success([
                'spt_version' => $sptVersionParam,
                'updates' => [],
                'blocked_updates' => [],
                'up_to_date' => [],
                'incompatible_with_spt' => [],
            ]);
        }

        // Load installed mod versions with relationships
        $installedModVersions = ModVersion::with(['mod', 'sptVersions', 'dependencies'])
            ->whereIn('id', $installedModVersionIds)
            ->get();

        // Perform update checking
        $updates = [];
        $blockedUpdates = [];
        $upToDate = [];
        $incompatibleWithSpt = [];

        foreach ($installedModVersions as $currentVersion) {
            $result = $this->checkForUpdate($currentVersion, $sptVersionParam, $installedModVersions);

            match ($result['status']) {
                'update_available' => $updates[] = $result['data'],
                'blocked' => $blockedUpdates[] = $result['data'],
                'up_to_date' => $upToDate[] = $result['data'],
                'incompatible' => $incompatibleWithSpt[] = $result['data'],
                default => null,
            };
        }

        return ApiResponse::success([
            'spt_version' => $sptVersionParam,
            'updates' => $updates,
            'blocked_updates' => $blockedUpdates,
            'up_to_date' => $upToDate,
            'incompatible_with_spt' => $incompatibleWithSpt,
        ]);
    }

    /**
     * Check for updates for a single mod version.
     *
     * @param  Collection<int, ModVersion>  $installedModVersions
     * @return array{status: string, data: array<string, mixed>}
     */
    private function checkForUpdate(ModVersion $currentVersion, string $sptVersion, Collection $installedModVersions): array
    {
        $isPrerelease = ! empty($currentVersion->version_labels);

        // Find candidate update
        $candidate = $this->findCandidateUpdate($currentVersion, $sptVersion, $isPrerelease);

        if ($candidate === null) {
            // Check if current version is compatible with target SPT
            $currentSptCompatible = $currentVersion->sptVersions()
                ->where('version', $sptVersion)
                ->whereNotNull('publish_date')
                ->where('publish_date', '<=', now())
                ->exists();

            if ($currentSptCompatible) {
                return [
                    'status' => 'up_to_date',
                    'data' => $this->formatUpToDateVersion($currentVersion),
                ];
            }

            return [
                'status' => 'incompatible',
                'data' => $this->formatIncompatibleVersion($currentVersion, 'no_version_for_spt'),
            ];
        }

        // Validate constraints
        $validationResult = $this->validateUpdateConstraints($currentVersion, $candidate, $sptVersion, $installedModVersions);

        if ($validationResult['valid']) {
            return [
                'status' => 'update_available',
                'data' => $this->formatUpdate($currentVersion, $candidate, $isPrerelease),
            ];
        }

        return [
            'status' => 'blocked',
            'data' => $this->formatBlockedUpdate($currentVersion, $candidate, $validationResult),
        ];
    }

    /**
     * Find a candidate update for a mod version.
     */
    private function findCandidateUpdate(ModVersion $currentVersion, string $sptVersion, bool $isPrerelease): ?ModVersion
    {
        $query = ModVersion::query()
            ->where('mod_id', $currentVersion->mod_id)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('disabled', false)
            ->whereHas('sptVersions', function (Builder $q) use ($sptVersion): void {
                $q->where('version', $sptVersion)
                    ->whereNotNull('publish_date')
                    ->where('publish_date', '<=', now());
            })
            ->whereHas('mod', function (Builder $q): void {
                $q->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->where('disabled', false);
            });

        // Stable versions never see prereleases
        if (! $isPrerelease) {
            $query->where('version_labels', '');
        }

        // Filter for versions newer than current
        $query->where(function (Builder $q) use ($currentVersion, $isPrerelease): void {
            // Versions with higher major, minor, or patch
            $q->where('version_major', '>', $currentVersion->version_major)
                ->orWhere(function (Builder $q2) use ($currentVersion): void {
                    $q2->where('version_major', $currentVersion->version_major)
                        ->where('version_minor', '>', $currentVersion->version_minor);
                })
                ->orWhere(function (Builder $q3) use ($currentVersion): void {
                    $q3->where('version_major', $currentVersion->version_major)
                        ->where('version_minor', $currentVersion->version_minor)
                        ->where('version_patch', '>', $currentVersion->version_patch);
                });

            // If on prerelease, also consider stable or newer prerelease of same version
            if ($isPrerelease) {
                $q->orWhere(function (Builder $q4) use ($currentVersion): void {
                    $q4->where('version_major', $currentVersion->version_major)
                        ->where('version_minor', $currentVersion->version_minor)
                        ->where('version_patch', $currentVersion->version_patch)
                        ->where(function (Builder $q5) use ($currentVersion): void {
                            // Stable version of same release
                            $q5->where('version_labels', '')
                                // Or newer prerelease
                                ->orWhere('version_labels', '>', $currentVersion->version_labels);
                        });
                });
            }
        });

        return $query->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->first();
    }

    /**
     * Validate that an update won't break dependency constraints.
     *
     * @param  Collection<int, ModVersion>  $installedModVersions
     * @return array{valid: bool, block_reason?: string, blocking_mods?: array<int, mixed>, missing_dependency?: array<string, mixed>, conflicting_mod?: array<string, mixed>}
     */
    private function validateUpdateConstraints(
        ModVersion $currentVersion,
        ModVersion $candidate,
        string $sptVersion,
        Collection $installedModVersions
    ): array {
        // Check incoming constraints (what depends on this mod)
        foreach ($installedModVersions as $otherMod) {
            if ($otherMod->id === $currentVersion->id) {
                continue;
            }

            $dependencies = $otherMod->dependencies()
                ->where('dependent_mod_id', $currentVersion->mod_id)
                ->get();

            foreach ($dependencies as $dep) {
                if (! Semver::satisfies($candidate->version, $dep->constraint)) {
                    return [
                        'valid' => false,
                        'block_reason' => 'dependency_constraint_violation',
                        'blocking_mods' => [
                            [
                                'mod_id' => $otherMod->mod_id,
                                'mod_guid' => $otherMod->mod->guid,
                                'mod_name' => $otherMod->mod->name,
                                'current_version' => $otherMod->version,
                                'constraint' => $dep->constraint,
                                'incompatible_with' => $candidate->version,
                            ],
                        ],
                    ];
                }
            }
        }

        // Check outgoing dependencies (what this update needs)
        $candidateDependencies = Dependency::query()
            ->where('dependable_id', $candidate->id)
            ->where('dependable_type', ModVersion::class)
            ->get();

        foreach ($candidateDependencies as $dep) {
            $satisfyingVersion = $this->dependencyService->findSatisfyingVersion(
                $dep->dependent_mod_id,
                $dep->constraint,
                $sptVersion
            );

            if ($satisfyingVersion === null) {
                return [
                    'valid' => false,
                    'block_reason' => 'missing_dependency',
                    'missing_dependency' => [
                        'mod_id' => $dep->dependent_mod_id,
                        'constraint' => $dep->constraint,
                    ],
                ];
            }
        }

        // Validate transitive dependencies
        $constraintsByModId = collect();
        $dependencyTree = $this->dependencyService->buildDependencyTree($candidate->id, collect(), $constraintsByModId);

        if (! is_null($dependencyTree)) {
            // Check for conflicts in transitive dependencies
            foreach ($installedModVersions as $installedMod) {
                $modId = $installedMod->mod_id;

                if ($constraintsByModId->has($modId)) {
                    $constraints = $constraintsByModId->get($modId);

                    foreach ($constraints as $constraint) {
                        if (! Semver::satisfies($installedMod->version, $constraint)) {
                            return [
                                'valid' => false,
                                'block_reason' => 'chain_dependency_conflict',
                                'conflicting_mod' => [
                                    'mod_id' => $installedMod->mod_id,
                                    'mod_guid' => $installedMod->mod->guid,
                                    'mod_name' => $installedMod->mod->name,
                                    'current_version' => $installedMod->version,
                                    'required_constraint' => $constraint,
                                ],
                            ];
                        }
                    }
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Format an update recommendation.
     *
     * @return array<string, mixed>
     */
    private function formatUpdate(ModVersion $currentVersion, ModVersion $recommendedVersion, bool $isPrerelease): array
    {
        return [
            'current_version' => [
                'id' => $currentVersion->id,
                'mod_id' => $currentVersion->mod_id,
                'guid' => $currentVersion->mod->guid,
                'name' => $currentVersion->mod->name,
                'slug' => $currentVersion->mod->slug,
                'version' => $currentVersion->version,
            ],
            'recommended_version' => [
                'id' => $recommendedVersion->id,
                'version' => $recommendedVersion->version,
                'link' => $recommendedVersion->downloadUrl(absolute: true),
                'content_length' => $recommendedVersion->content_length,
                'fika_compatibility' => $recommendedVersion->fika_compatibility->value,
                'spt_versions' => $recommendedVersion->sptVersions->pluck('version')->toArray(),
            ],
            'update_reason' => $isPrerelease ? 'newer_prerelease_available' : 'newer_version_available',
        ];
    }

    /**
     * Format a blocked update.
     *
     * @param  array{valid: bool, block_reason?: string, blocking_mods?: array<int, mixed>, missing_dependency?: array<string, mixed>, conflicting_mod?: array<string, mixed>}  $validationResult
     * @return array<string, mixed>
     */
    private function formatBlockedUpdate(ModVersion $currentVersion, ModVersion $latestVersion, array $validationResult): array
    {
        $data = [
            'current_version' => [
                'id' => $currentVersion->id,
                'mod_id' => $currentVersion->mod_id,
                'guid' => $currentVersion->mod->guid,
                'name' => $currentVersion->mod->name,
                'version' => $currentVersion->version,
            ],
            'latest_version' => [
                'id' => $latestVersion->id,
                'version' => $latestVersion->version,
                'spt_versions' => $latestVersion->sptVersions->pluck('version')->toArray(),
            ],
            'block_reason' => $validationResult['block_reason'],
        ];

        if (isset($validationResult['blocking_mods'])) {
            $data['blocking_mods'] = $validationResult['blocking_mods'];
        }

        return $data;
    }

    /**
     * Format an up-to-date version.
     *
     * @return array<string, mixed>
     */
    private function formatUpToDateVersion(ModVersion $version): array
    {
        return [
            'id' => $version->id,
            'mod_id' => $version->mod_id,
            'guid' => $version->mod->guid,
            'name' => $version->mod->name,
            'version' => $version->version,
            'spt_versions' => $version->sptVersions->pluck('version')->toArray(),
        ];
    }

    /**
     * Format an incompatible version.
     *
     * @return array<string, mixed>
     */
    private function formatIncompatibleVersion(ModVersion $version, string $reason): array
    {
        return [
            'id' => $version->id,
            'mod_id' => $version->mod_id,
            'guid' => $version->mod->guid,
            'name' => $version->mod->name,
            'version' => $version->version,
            'reason' => $reason,
            'latest_compatible_version' => null,
        ];
    }
}
