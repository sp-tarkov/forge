<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Support\Api\V0\QueryBuilder\ModDependencyTreeQueryBuilder;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DependencyService
{
    /**
     * Parse mod identifier:version pairs from query parameter.
     *
     * @return Collection<int, array{identifier: string, version: string, is_mod_id: bool}>
     */
    public function parseModVersionPairs(string $modsParam): Collection
    {
        return collect(explode(',', $modsParam))
            ->map(fn (string $pair): string => mb_trim($pair))
            ->reject(fn (string $pair): bool => $pair === '' || $pair === '0')
            ->unique()
            ->map(function (string $pair): ?array {
                $parts = explode(':', $pair);
                if (count($parts) !== 2) {
                    return null;
                }

                $identifier = mb_trim($parts[0]);
                $version = mb_trim($parts[1]);

                if ($identifier === '' || $identifier === '0' || ($version === '' || $version === '0')) {
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
    }

    /**
     * Parse addon identifier:version pairs from query parameter.
     *
     * @return Collection<int, array{identifier: string, version: string, is_addon_id: bool}>
     */
    public function parseAddonVersionPairs(string $addonsParam): Collection
    {
        return collect(explode(',', $addonsParam))
            ->map(fn (string $pair): string => mb_trim($pair))
            ->reject(fn (string $pair): bool => $pair === '' || $pair === '0')
            ->unique()
            ->map(function (string $pair): ?array {
                $parts = explode(':', $pair);
                if (count($parts) !== 2) {
                    return null;
                }

                $identifier = mb_trim($parts[0]);
                $version = mb_trim($parts[1]);

                if ($identifier === '' || $identifier === '0' || ($version === '' || $version === '0')) {
                    return null;
                }

                // Determine if identifier is numeric (addon_id) or string (slug)
                $isNumeric = is_numeric($identifier) && (int) $identifier > 0;

                return [
                    'identifier' => $identifier,
                    'version' => $version,
                    'is_addon_id' => $isNumeric,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Resolve mod version IDs from identifier:version pairs with public visibility checks.
     *
     * @param  Collection<int, array{identifier: string, version: string, is_mod_id: bool}>  $modVersionPairs
     * @return Collection<int, int>
     */
    public function resolveModVersionIds(Collection $modVersionPairs): Collection
    {
        /** @var Collection<int, int> $queriedModVersionIds */
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

            if (is_int($versionId)) {
                $queriedModVersionIds->push($versionId);
            }
        }

        return $queriedModVersionIds;
    }

    /**
     * Resolve addon version IDs from identifier:version pairs with public visibility checks.
     *
     * @param  Collection<int, array{identifier: string, version: string, is_addon_id: bool}>  $addonVersionPairs
     * @return Collection<int, int>
     */
    public function resolveAddonVersionIds(Collection $addonVersionPairs): Collection
    {
        /** @var Collection<int, int> $queriedAddonVersionIds */
        $queriedAddonVersionIds = collect();

        foreach ($addonVersionPairs as $pair) {
            $query = DB::table('addon_versions')
                ->join('addons', 'addon_versions.addon_id', '=', 'addons.id')
                ->where('addon_versions.version', $pair['version'])
                ->whereNotNull('addon_versions.published_at')
                ->where('addon_versions.published_at', '<=', now())
                ->where('addon_versions.disabled', false)
                ->whereNotNull('addons.published_at')
                ->where('addons.published_at', '<=', now())
                ->where('addons.disabled', false);

            if ($pair['is_addon_id']) {
                $query->where('addons.id', (int) $pair['identifier']);
            } else {
                $query->where('addons.slug', $pair['identifier']);
            }

            $versionId = $query->value('addon_versions.id');

            if (is_int($versionId)) {
                $queriedAddonVersionIds->push($versionId);
            }
        }

        return $queriedAddonVersionIds;
    }

    /**
     * Apply public visibility constraints to a mod versions query.
     *
     * @param  Builder<ModVersion>  $query
     * @return Builder<ModVersion>
     */
    public function wherePubliclyVisible(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('disabled', false)
            ->whereHas('latestSptVersion')
            ->whereHas('mod', function (Builder $q): void {
                $q->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->where('disabled', false);
            });
    }

    /**
     * Recursively build the dependency tree for a mod version with circular dependency prevention.
     *
     * @param  Collection<int, int>  $processedVersionIds
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId
     * @return array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}>|null
     */
    public function buildDependencyTree(int $modVersionId, Collection $processedVersionIds, Collection $constraintsByModId): ?array
    {
        // Check for circular dependencies
        if ($processedVersionIds->contains($modVersionId)) {
            return null;
        }

        // Mark this version as processed
        $processedVersionIds = $processedVersionIds->push($modVersionId);

        // Get the latest resolved version for each dependency by semantic version
        $dependencies = DB::table('dependencies_resolved')
            ->select(
                'dependencies.dependent_mod_id',
                'dependencies.constraint',
                DB::raw('MAX(resolved_versions.id) as latest_version_id')
            )
            ->join('dependencies', 'dependencies_resolved.dependency_id', '=', 'dependencies.id')
            ->join('mod_versions as resolved_versions', function (JoinClause $join): void {
                $join->on('dependencies_resolved.resolved_mod_version_id', '=', 'resolved_versions.id')
                    ->whereNotNull('resolved_versions.published_at')
                    ->where('resolved_versions.published_at', '<=', now())
                    ->where('resolved_versions.disabled', false);
            })
            ->join('mods', function (JoinClause $join): void {
                $join->on('dependencies.dependent_mod_id', '=', 'mods.id')
                    ->whereNotNull('mods.published_at')
                    ->where('mods.published_at', '<=', now())
                    ->where('mods.disabled', false);
            })
            ->joinSub(
                DB::table('mod_versions as mv')
                    ->select('mv.mod_id', 'mv.id')
                    ->selectRaw('ROW_NUMBER() OVER (
                        PARTITION BY mv.mod_id
                        ORDER BY mv.version_major DESC, mv.version_minor DESC, mv.version_patch DESC,
                                 CASE WHEN mv.version_labels = ? THEN 0 ELSE 1 END, mv.version_labels
                    ) as rn', [''])
                    ->join('dependencies_resolved as rd', 'mv.id', '=', 'rd.resolved_mod_version_id')
                    ->where('rd.dependable_id', $modVersionId)
                    ->where('rd.dependable_type', ModVersion::class)
                    ->whereNotNull('mv.published_at')
                    ->where('mv.published_at', '<=', now())
                    ->where('mv.disabled', false),
                'ranked',
                function (JoinClause $join): void {
                    $join->on('resolved_versions.id', '=', 'ranked.id')
                        ->where('ranked.rn', '=', 1);
                }
            )
            ->where('dependencies_resolved.dependable_id', $modVersionId)
            ->where('dependencies_resolved.dependable_type', ModVersion::class)
            ->groupBy('dependencies.dependent_mod_id', 'dependencies.constraint')
            ->get();

        if ($dependencies->isEmpty()) {
            return [];
        }

        // Store constraints for each mod
        foreach ($dependencies as $dependency) {
            /** @var int $modId */
            $modId = $dependency->dependent_mod_id;
            if (! $constraintsByModId->has($modId)) {
                /** @var Collection<int, string> $emptyCollection */
                $emptyCollection = collect();
                $constraintsByModId->put($modId, $emptyCollection);
            }

            /** @var string $constraint */
            $constraint = $dependency->constraint;
            $constraintsByModId->get($modId)?->push($constraint);
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
            ->with(['versions' => function (Relation $query) use ($versionIds): void {
                $query->whereIn('id', $versionIds);
            }])
            ->get();

        // Build a map of mod_id => latest_version_id from our dependencies
        $modVersionMap = $dependencies->pluck('latest_version_id', 'dependent_mod_id');

        // Build tree nodes for each mod
        return $mods->map(function (Mod $mod) use ($modVersionMap, $processedVersionIds, $constraintsByModId): array {
            /** @var int $latestVersionId */
            $latestVersionId = $modVersionMap[$mod->id] ?? 0;
            $latestVersion = $latestVersionId ? $mod->versions->firstWhere('id', $latestVersionId) : null;

            // Recursively build dependencies for this version
            $subDependencies = $latestVersionId
                ? $this->buildDependencyTree($latestVersionId, $processedVersionIds, $constraintsByModId)
                : [];

            return [
                'mod' => $mod,
                'latest_version_id' => $latestVersionId,
                'latest_version' => $latestVersion,
                'dependencies' => $subDependencies ?? [],
            ];
        })->values()->all();
    }

    /**
     * Recursively build the dependency tree for an addon version with circular dependency prevention.
     *
     * @param  Collection<int, int>  $processedVersionIds
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId
     * @return array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}>
     */
    public function buildAddonDependencyTree(int $addonVersionId, Collection $processedVersionIds, Collection $constraintsByModId): array
    {
        // Get the latest resolved version for each dependency by semantic version
        $dependencies = DB::table('dependencies_resolved')
            ->select(
                'dependencies.dependent_mod_id',
                'dependencies.constraint',
                DB::raw('MAX(resolved_versions.id) as latest_version_id')
            )
            ->join('dependencies', 'dependencies_resolved.dependency_id', '=', 'dependencies.id')
            ->join('mod_versions as resolved_versions', function (JoinClause $join): void {
                $join->on('dependencies_resolved.resolved_mod_version_id', '=', 'resolved_versions.id')
                    ->whereNotNull('resolved_versions.published_at')
                    ->where('resolved_versions.published_at', '<=', now())
                    ->where('resolved_versions.disabled', false);
            })
            ->join('mods', function (JoinClause $join): void {
                $join->on('dependencies.dependent_mod_id', '=', 'mods.id')
                    ->whereNotNull('mods.published_at')
                    ->where('mods.published_at', '<=', now())
                    ->where('mods.disabled', false);
            })
            ->joinSub(
                DB::table('mod_versions as mv')
                    ->select('mv.mod_id', 'mv.id')
                    ->selectRaw('ROW_NUMBER() OVER (
                        PARTITION BY mv.mod_id
                        ORDER BY mv.version_major DESC, mv.version_minor DESC, mv.version_patch DESC,
                                 CASE WHEN mv.version_labels = ? THEN 0 ELSE 1 END, mv.version_labels
                    ) as rn', [''])
                    ->join('dependencies_resolved as rd', 'mv.id', '=', 'rd.resolved_mod_version_id')
                    ->where('rd.dependable_id', $addonVersionId)
                    ->where('rd.dependable_type', AddonVersion::class)
                    ->whereNotNull('mv.published_at')
                    ->where('mv.published_at', '<=', now())
                    ->where('mv.disabled', false),
                'ranked',
                function (JoinClause $join): void {
                    $join->on('resolved_versions.id', '=', 'ranked.id')
                        ->where('ranked.rn', '=', 1);
                }
            )
            ->where('dependencies_resolved.dependable_id', $addonVersionId)
            ->where('dependencies_resolved.dependable_type', AddonVersion::class)
            ->groupBy('dependencies.dependent_mod_id', 'dependencies.constraint')
            ->get();

        if ($dependencies->isEmpty()) {
            return [];
        }

        // Store constraints for each mod
        foreach ($dependencies as $dependency) {
            /** @var int $modId */
            $modId = $dependency->dependent_mod_id;
            if (! $constraintsByModId->has($modId)) {
                /** @var Collection<int, string> $emptyCollection */
                $emptyCollection = collect();
                $constraintsByModId->put($modId, $emptyCollection);
            }

            /** @var string $constraint */
            $constraint = $dependency->constraint;
            $constraintsByModId->get($modId)?->push($constraint);
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
            ->with(['versions' => function (Relation $query) use ($versionIds): void {
                $query->whereIn('id', $versionIds);
            }])
            ->get();

        // Build a map of mod_id => latest_version_id from our dependencies
        $modVersionMap = $dependencies->pluck('latest_version_id', 'dependent_mod_id');

        // Build tree nodes for each mod (addon dependencies don't recurse - addons only depend on mods)
        return $mods->map(function (Mod $mod) use ($modVersionMap, $processedVersionIds, $constraintsByModId): array {
            /** @var int $latestVersionId */
            $latestVersionId = $modVersionMap[$mod->id] ?? 0;
            $latestVersion = $latestVersionId ? $mod->versions->firstWhere('id', $latestVersionId) : null;

            // Recursively build dependencies for this mod version (mods can depend on other mods)
            $subDependencies = $latestVersionId
                ? $this->buildDependencyTree($latestVersionId, $processedVersionIds, $constraintsByModId)
                : [];

            return [
                'mod' => $mod,
                'latest_version_id' => $latestVersionId,
                'latest_version' => $latestVersion,
                'dependencies' => $subDependencies ?? [],
            ];
        })->values()->all();
    }

    /**
     * Find a mod version that satisfies the given constraint for a specific SPT version.
     */
    public function findSatisfyingVersion(int $modId, string $constraint, string $sptVersion): ?ModVersion
    {
        $versions = ModVersion::query()
            ->where('mod_id', $modId)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('disabled', false)
            ->whereHas('sptVersions', function (Builder $q) use ($sptVersion): void {
                $q->where('version', $sptVersion)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->whereHas('mod', function (Builder $q): void {
                $q->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->where('disabled', false);
            })
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->get();

        // Find the highest version that satisfies the constraint
        $satisfyingVersions = $versions->filter(fn (ModVersion $version): bool => Semver::satisfies($version->version, $constraint));

        return $satisfyingVersions->first();
    }

    /**
     * Collect all constraints from a dependency tree into a collection indexed by mod ID.
     *
     * @param  array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}>  $dependencyTree
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId
     */
    public function collectAllConstraints(array $dependencyTree, Collection $constraintsByModId): void
    {
        // Collect all version IDs from the tree upfront to batch-query dependencies
        $versionIds = $this->collectVersionIdsFromTree($dependencyTree);

        if ($versionIds === []) {
            return;
        }

        // Single query for all dependencies instead of one per tree node
        $allDependencies = DB::table('dependencies')
            ->whereIn('dependable_id', $versionIds)
            ->where('dependable_type', ModVersion::class)
            ->get()
            ->groupBy('dependable_id');

        $this->applyConstraintsFromTree($dependencyTree, $allDependencies, $constraintsByModId);
    }

    /**
     * Recursively collect all version IDs from a dependency tree.
     *
     * @param  array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}>  $dependencyTree
     * @return array<int, int>
     */
    private function collectVersionIdsFromTree(array $dependencyTree): array
    {
        $ids = [];

        foreach ($dependencyTree as $node) {
            if ($node['latest_version_id']) {
                $ids[] = $node['latest_version_id'];
            }

            if (! empty($node['dependencies'])) {
                /** @var array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}> $subDependencies */
                $subDependencies = $node['dependencies'];
                $ids = [...$ids, ...$this->collectVersionIdsFromTree($subDependencies)];
            }
        }

        return $ids;
    }

    /**
     * Apply pre-fetched dependency constraints to the collection.
     *
     * @param  array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}>  $dependencyTree
     * @param  Collection<int, Collection<int, stdClass>>  $allDependencies
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId
     */
    private function applyConstraintsFromTree(array $dependencyTree, Collection $allDependencies, Collection $constraintsByModId): void
    {
        foreach ($dependencyTree as $node) {
            $versionId = $node['latest_version_id'];

            if ($versionId && $allDependencies->has($versionId)) {
                foreach ($allDependencies->get($versionId, collect()) as $dep) {
                    /** @var int $depModId */
                    $depModId = $dep->dependent_mod_id;
                    if (! $constraintsByModId->has($depModId)) {
                        /** @var Collection<int, string> $emptyCollection */
                        $emptyCollection = collect();
                        $constraintsByModId->put($depModId, $emptyCollection);
                    }

                    /** @var string $depConstraint */
                    $depConstraint = $dep->constraint;
                    $constraintsByModId->get($depModId)?->push($depConstraint);
                }
            }

            if (! empty($node['dependencies'])) {
                /** @var array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}> $subDependencies */
                $subDependencies = $node['dependencies'];
                $this->applyConstraintsFromTree($subDependencies, $allDependencies, $constraintsByModId);
            }
        }
    }
}
