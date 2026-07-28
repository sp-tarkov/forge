<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Support\Api\V0\QueryBuilder\ModDependencyTreeQueryBuilder;
use App\Support\DataTransferObjects\DependencyTreeNode;
use App\Support\DataTransferObjects\QueriedVersion;
use App\Support\VersionMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DependencyService
{
    /**
     * Per-instance memo of the resolved dependency rows for mod versions, keyed by "modVersionId:sptVersionId".
     *
     * @var array<string, Collection<int, stdClass>>
     */
    private array $dependencyRowsByVersionId = [];

    /**
     * Per-instance memo of the resolved dependency rows for addon versions, keyed by "addonVersionId:sptVersionId".
     *
     * @var array<string, Collection<int, stdClass>>
     */
    private array $addonDependencyRowsByVersionId = [];

    /**
     * Per-instance memo of the mods loaded for dependency tree nodes, keyed by the imploded mod and version ID lists.
     *
     * @var array<string, EloquentCollection<int, Mod>>
     */
    private array $dependencyModsByModIds = [];

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
        return $this->resolveModVersions($modVersionPairs)
            ->map(fn (QueriedVersion $queriedVersion): int => $queriedVersion->versionId);
    }

    /**
     * Resolve mod versions from identifier:version pairs with public visibility checks, pairing each resolved mod
     * version ID with the queried identifier:version pair strings that matched it.
     *
     * @param  Collection<int, array{identifier: string, version: string, is_mod_id: bool}>  $modVersionPairs
     * @return Collection<int, QueriedVersion>
     */
    public function resolveModVersions(Collection $modVersionPairs): Collection
    {
        if ($modVersionPairs->isEmpty()) {
            /** @var Collection<int, QueriedVersion> */
            return collect();
        }

        $rows = DB::table('mod_versions')
            ->join('mods', 'mod_versions.mod_id', '=', 'mods.id')
            ->select('mod_versions.id', 'mod_versions.version', 'mods.id as mod_id', 'mods.guid')
            ->where(function (\Illuminate\Database\Query\Builder $query) use ($modVersionPairs): void {
                foreach ($modVersionPairs as $pair) {
                    $query->orWhere(function (\Illuminate\Database\Query\Builder $q) use ($pair): void {
                        $q->where('mod_versions.version', $pair['version']);
                        if ($pair['is_mod_id']) {
                            $q->where('mods.id', (int) $pair['identifier']);
                        } else {
                            $q->where('mods.guid', Str::lower($pair['identifier']));
                        }
                    });
                }
            })
            ->whereNotNull('mod_versions.published_at')
            ->where('mod_versions.published_at', '<=', now())
            ->where('mod_versions.disabled', false)
            ->whereNotNull('mods.published_at')
            ->where('mods.published_at', '<=', now())
            ->where('mods.disabled', false)
            ->get();

        return $rows
            ->map(fn (stdClass $row): ?QueriedVersion => is_numeric($row->id)
                ? new QueriedVersion((int) $row->id, $this->matchingModPairKeys($row, $modVersionPairs))
                : null)
            ->filter()
            ->values();
    }

    /**
     * Resolve addon version IDs from identifier:version pairs with public visibility checks.
     *
     * @param  Collection<int, array{identifier: string, version: string, is_addon_id: bool}>  $addonVersionPairs
     * @return Collection<int, int>
     */
    public function resolveAddonVersionIds(Collection $addonVersionPairs): Collection
    {
        return $this->resolveAddonVersions($addonVersionPairs)
            ->map(fn (QueriedVersion $queriedVersion): int => $queriedVersion->versionId);
    }

    /**
     * Resolve addon versions from identifier:version pairs with public visibility checks, pairing each resolved
     * addon version ID with the queried identifier:version pair strings that matched it.
     *
     * @param  Collection<int, array{identifier: string, version: string, is_addon_id: bool}>  $addonVersionPairs
     * @return Collection<int, QueriedVersion>
     */
    public function resolveAddonVersions(Collection $addonVersionPairs): Collection
    {
        if ($addonVersionPairs->isEmpty()) {
            /** @var Collection<int, QueriedVersion> */
            return collect();
        }

        $rows = DB::table('addon_versions')
            ->join('addons', 'addon_versions.addon_id', '=', 'addons.id')
            ->select('addon_versions.id', 'addon_versions.version', 'addons.id as addon_id', 'addons.slug')
            ->where(function (\Illuminate\Database\Query\Builder $query) use ($addonVersionPairs): void {
                foreach ($addonVersionPairs as $pair) {
                    $query->orWhere(function (\Illuminate\Database\Query\Builder $q) use ($pair): void {
                        $q->where('addon_versions.version', $pair['version']);
                        if ($pair['is_addon_id']) {
                            $q->where('addons.id', (int) $pair['identifier']);
                        } else {
                            $q->where('addons.slug', $pair['identifier']);
                        }
                    });
                }
            })
            ->whereNotNull('addon_versions.published_at')
            ->where('addon_versions.published_at', '<=', now())
            ->where('addon_versions.disabled', false)
            ->whereNotNull('addons.published_at')
            ->where('addons.published_at', '<=', now())
            ->where('addons.disabled', false)
            ->get();

        return $rows
            ->map(fn (stdClass $row): ?QueriedVersion => is_numeric($row->id)
                ? new QueriedVersion((int) $row->id, $this->matchingAddonPairKeys($row, $addonVersionPairs))
                : null)
            ->filter()
            ->values();
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
     * Resolve the grouped dependency trees for a set of queried mod versions, keyed by the queried
     * identifier:version pair strings.
     *
     * @param  Collection<int, QueriedVersion>  $queriedVersions
     * @return array<string, list<DependencyTreeNode>>
     */
    public function resolveGroupedModDependencies(Collection $queriedVersions, int $sptVersionId): array
    {
        return $this->resolveGroupedDependencies($queriedVersions, $sptVersionId, forAddons: false);
    }

    /**
     * Resolve the grouped dependency trees for a set of queried addon versions, keyed by the queried
     * identifier:version pair strings.
     *
     * @param  Collection<int, QueriedVersion>  $queriedVersions
     * @return array<string, list<DependencyTreeNode>>
     */
    public function resolveGroupedAddonDependencies(Collection $queriedVersions, int $sptVersionId): array
    {
        return $this->resolveGroupedDependencies($queriedVersions, $sptVersionId, forAddons: true);
    }

    /**
     * Get the resolved dependency rows for a mod version, memoized per instance. Each row holds the dependent mod
     * ID, the raw constraint, and the highest published resolved version ID. With an SPT version ID, resolution is
     * limited to SPT-compatible versions and rows with a null latest_version_id are kept for visible dependency
     * mods that have none.
     *
     * @return Collection<int, stdClass>
     */
    public function dependencyRowsForModVersion(int $modVersionId, ?int $sptVersionId = null): Collection
    {
        return $this->dependencyRowsByVersionId[$modVersionId.':'.($sptVersionId ?? 'all')] ??=
            $this->dependencyRows($modVersionId, ModVersion::class, $sptVersionId);
    }

    /**
     * Get the resolved dependency rows for an addon version, memoized per instance, in the same shape as
     * dependencyRowsForModVersion().
     *
     * @return Collection<int, stdClass>
     */
    public function dependencyRowsForAddonVersion(int $addonVersionId, ?int $sptVersionId = null): Collection
    {
        return $this->addonDependencyRowsByVersionId[$addonVersionId.':'.($sptVersionId ?? 'all')] ??=
            $this->dependencyRows($addonVersionId, AddonVersion::class, $sptVersionId);
    }

    /**
     * Recursively build the dependency tree for a mod version with circular dependency prevention.
     *
     * @param  Collection<int, int>  $processedVersionIds
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId
     * @return array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}>|null
     */
    public function buildDependencyTree(int $modVersionId, Collection $processedVersionIds, Collection $constraintsByModId, ?int $sptVersionId = null): ?array
    {
        // Check for circular dependencies
        if ($processedVersionIds->contains($modVersionId)) {
            return null;
        }

        // Mark this version as processed
        $processedVersionIds = $processedVersionIds->push($modVersionId);

        $dependencies = $this->dependencyRowsForModVersion($modVersionId, $sptVersionId);

        if ($dependencies->isEmpty()) {
            return [];
        }

        $this->recordConstraints($dependencies, $constraintsByModId);

        return $this->buildTreeNodes($dependencies, $processedVersionIds, $constraintsByModId, $sptVersionId);
    }

    /**
     * Build the dependency tree for an addon version, recursing into the mod dependency trees of each resolved
     * version.
     *
     * @param  Collection<int, int>  $processedVersionIds
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId
     * @return array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}>
     */
    public function buildAddonDependencyTree(int $addonVersionId, Collection $processedVersionIds, Collection $constraintsByModId, ?int $sptVersionId = null): array
    {
        $dependencies = $this->dependencyRowsForAddonVersion($addonVersionId, $sptVersionId);

        if ($dependencies->isEmpty()) {
            return [];
        }

        $this->recordConstraints($dependencies, $constraintsByModId);

        return $this->buildTreeNodes($dependencies, $processedVersionIds, $constraintsByModId, $sptVersionId);
    }

    /**
     * Get the published, visible versions of a mod that are compatible with a specific SPT version, ordered newest
     * first. The result depends only on the mod and the SPT version, not on any dependency constraint, so a caller
     * resolving many constraints against the same target can fetch this once and apply each constraint in memory
     * instead of issuing one query per constraint.
     *
     * @return EloquentCollection<int, ModVersion>
     */
    public function publishedVersionsForSpt(int $modId, string $sptVersion): EloquentCollection
    {
        return $this->publishedVersionsForSptQuery($sptVersion)
            ->where('mod_id', $modId)
            ->get();
    }

    /**
     * Get the published, visible versions of many mods that are compatible with a specific SPT version in a single
     * query. Versions are ordered newest first within each mod, so grouping the result by mod ID preserves the same
     * per-mod ordering as publishedVersionsForSpt().
     *
     * @param  list<int>  $modIds
     * @return EloquentCollection<int, ModVersion>
     */
    public function publishedVersionsForSptByModIds(array $modIds, string $sptVersion): EloquentCollection
    {
        if ($modIds === []) {
            return new EloquentCollection;
        }

        return $this->publishedVersionsForSptQuery($sptVersion)
            ->whereIn('mod_id', $modIds)
            ->get();
    }

    /**
     * Find the highest mod version that satisfies the given constraint for a specific SPT version.
     */
    public function findSatisfyingVersion(int $modId, string $constraint, string $sptVersion): ?ModVersion
    {
        return $this->publishedVersionsForSpt($modId, $sptVersion)
            ->first(fn (ModVersion $version): bool => VersionMatcher::satisfies($version->version, $constraint));
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
     * Build the grouped dependency trees for queried mod or addon versions: build every raw tree while collecting
     * constraints and version candidates globally, compute one version pick per dependency mod, then render each
     * queried pair's tree from its dependency rows using those picks.
     *
     * @param  Collection<int, QueriedVersion>  $queriedVersions
     * @return array<string, list<DependencyTreeNode>>
     */
    private function resolveGroupedDependencies(Collection $queriedVersions, int $sptVersionId, bool $forAddons): array
    {
        /** @var Collection<int, Collection<int, string>> $constraintsByModId */
        $constraintsByModId = collect();
        /** @var array<int, array<int, array{mod: Mod, version: ModVersion}>> $candidatesByModId */
        $candidatesByModId = [];
        /** @var array<int, Mod> $modsById */
        $modsById = [];

        foreach ($queriedVersions as $queriedVersion) {
            /** @var Collection<int, int> $processedVersionIds */
            $processedVersionIds = collect();
            $tree = $forAddons
                ? $this->buildAddonDependencyTree($queriedVersion->versionId, $processedVersionIds, $constraintsByModId, $sptVersionId)
                : $this->buildDependencyTree($queriedVersion->versionId, $processedVersionIds, $constraintsByModId, $sptVersionId);

            $this->collectCandidates($tree ?? [], $candidatesByModId, $modsById);
        }

        $picksByModId = $this->computeGlobalPicks($candidatesByModId, $constraintsByModId);

        $groups = [];

        foreach ($queriedVersions as $queriedVersion) {
            $rows = $forAddons
                ? $this->dependencyRowsForAddonVersion($queriedVersion->versionId, $sptVersionId)
                : $this->dependencyRowsForModVersion($queriedVersion->versionId, $sptVersionId);

            $nodes = $this->renderDependencyNodes(
                $rows,
                $picksByModId,
                $modsById,
                $candidatesByModId,
                $sptVersionId,
                $forAddons ? [] : [$queriedVersion->versionId],
            );

            foreach ($queriedVersion->pairKeys as $pairKey) {
                $groups[$pairKey] = $nodes;
            }
        }

        return $groups;
    }

    /**
     * Collect the mods and mod version candidates materialized in a raw dependency tree, keyed by mod ID.
     *
     * @param  array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}>  $tree
     * @param  array<int, array<int, array{mod: Mod, version: ModVersion}>>  $candidatesByModId
     * @param  array<int, Mod>  $modsById
     */
    private function collectCandidates(array $tree, array &$candidatesByModId, array &$modsById): void
    {
        foreach ($tree as $node) {
            $mod = $node['mod'];
            $modsById[$mod->id] ??= $mod;

            if ($node['latest_version'] instanceof ModVersion) {
                $candidatesByModId[$mod->id][$node['latest_version']->id] = [
                    'mod' => $mod,
                    'version' => $node['latest_version'],
                ];
            }

            if ($node['dependencies'] !== []) {
                /** @var array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}> $subDependencies */
                $subDependencies = $node['dependencies'];
                $this->collectCandidates($subDependencies, $candidatesByModId, $modsById);
            }
        }
    }

    /**
     * Compute the globally chosen version for each dependency mod: the highest candidate satisfying every collected
     * constraint, or a conflict marker when no candidate satisfies all constraints.
     *
     * @param  array<int, array<int, array{mod: Mod, version: ModVersion}>>  $candidatesByModId
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId
     * @return array<int, array{version: ModVersion|null, conflict: bool}>
     */
    private function computeGlobalPicks(array $candidatesByModId, Collection $constraintsByModId): array
    {
        $picks = [];

        foreach ($candidatesByModId as $modId => $candidates) {
            $constraints = $constraintsByModId->get($modId) ?? collect();

            $satisfying = array_values(array_filter(
                $candidates,
                fn (array $candidate): bool => $constraints->every(fn (string $constraint): bool => VersionMatcher::satisfies($candidate['version']->version, $constraint))
            ));

            if ($satisfying === []) {
                $picks[$modId] = ['version' => null, 'conflict' => true];

                continue;
            }

            $versionStrings = array_map(fn (array $candidate): string => $candidate['version']->version, $satisfying);
            $highestVersion = VersionMatcher::rsort($versionStrings)[0];

            $picked = null;
            foreach ($satisfying as $candidate) {
                if ($candidate['version']->version === $highestVersion) {
                    $picked = $candidate['version'];
                    break;
                }
            }

            $picks[$modId] = ['version' => $picked, 'conflict' => false];
        }

        return $picks;
    }

    /**
     * Render the dependency nodes for one level of dependency rows, applying the global version pick per mod, a
     * per-path cycle guard on version IDs, and one node per mod per level.
     *
     * @param  Collection<int, stdClass>  $rows
     * @param  array<int, array{version: ModVersion|null, conflict: bool}>  $picksByModId
     * @param  array<int, Mod>  $modsById
     * @param  array<int, array<int, array{mod: Mod, version: ModVersion}>>  $candidatesByModId
     * @param  list<int>  $pathVersionIds
     * @return list<DependencyTreeNode>
     */
    private function renderDependencyNodes(Collection $rows, array $picksByModId, array &$modsById, array &$candidatesByModId, int $sptVersionId, array $pathVersionIds): array
    {
        $nodes = [];
        $renderedModIds = [];

        foreach ($rows as $row) {
            $modId = is_numeric($row->dependent_mod_id) ? (int) $row->dependent_mod_id : 0;
            if ($modId === 0) {
                continue;
            }

            if (isset($renderedModIds[$modId])) {
                continue;
            }

            $renderedModIds[$modId] = true;

            $localVersionId = is_numeric($row->latest_version_id) ? (int) $row->latest_version_id : 0;
            $pick = $picksByModId[$modId] ?? null;

            if ($pick !== null && $pick['version'] instanceof ModVersion) {
                $version = $pick['version'];
                $conflict = false;
            } else {
                $version = $localVersionId !== 0 ? $this->dependencyVersionModel($modId, $localVersionId, $modsById, $candidatesByModId) : null;
                $conflict = $pick !== null && $pick['conflict'];
            }

            $mod = $this->dependencyModModel($modId, $modsById);
            if (! $mod instanceof Mod) {
                continue;
            }

            $dependencies = [];
            if ($version instanceof ModVersion && ! in_array($version->id, $pathVersionIds, true)) {
                $dependencies = $this->renderDependencyNodes(
                    $this->dependencyRowsForModVersion($version->id, $sptVersionId),
                    $picksByModId,
                    $modsById,
                    $candidatesByModId,
                    $sptVersionId,
                    [...$pathVersionIds, $version->id],
                );
            }

            $nodes[] = new DependencyTreeNode($mod, $version, $conflict, $dependencies);
        }

        return $nodes;
    }

    /**
     * Get the Mod model for a dependency mod ID, loading and registering it when not already collected.
     *
     * @param  array<int, Mod>  $modsById
     */
    private function dependencyModModel(int $modId, array &$modsById): ?Mod
    {
        if (isset($modsById[$modId])) {
            return $modsById[$modId];
        }

        $mod = $this->dependencyModsForModIds([$modId], [])->first();
        if ($mod instanceof Mod) {
            $modsById[$modId] = $mod;
        }

        return $mod;
    }

    /**
     * Get the ModVersion model for a dependency mod and version ID, loading and registering it when not already
     * collected as a candidate.
     *
     * @param  array<int, Mod>  $modsById
     * @param  array<int, array<int, array{mod: Mod, version: ModVersion}>>  $candidatesByModId
     */
    private function dependencyVersionModel(int $modId, int $versionId, array &$modsById, array &$candidatesByModId): ?ModVersion
    {
        $existing = $candidatesByModId[$modId][$versionId]['version'] ?? null;
        if ($existing instanceof ModVersion) {
            return $existing;
        }

        $mod = $this->dependencyModsForModIds([$modId], [$versionId])->first();
        if (! $mod instanceof Mod) {
            return null;
        }

        $modsById[$modId] ??= $mod;

        $version = $mod->versions->firstWhere('id', $versionId);
        if ($version instanceof ModVersion) {
            $version->setRelation('mod', $mod);
            $candidatesByModId[$modId][$versionId] = ['mod' => $mod, 'version' => $version];
        }

        return $version;
    }

    /**
     * Query the resolved dependency rows for a dependable version. Without an SPT version ID, one row is returned
     * per dependency constraint whose resolutions include the mod's overall highest published resolved version.
     * With an SPT version ID, one row is returned per dependency constraint for every visible dependency mod, with
     * the highest SPT-compatible published resolved version or a null latest_version_id when none exists.
     *
     * @param  class-string  $dependableType
     * @return Collection<int, stdClass>
     */
    private function dependencyRows(int $dependableId, string $dependableType, ?int $sptVersionId): Collection
    {
        if ($sptVersionId === null) {
            return DB::table('dependencies_resolved')
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
                    $this->rankedResolvedVersionsQuery($dependableId, $dependableType, null),
                    'ranked',
                    function (JoinClause $join): void {
                        $join->on('resolved_versions.id', '=', 'ranked.id')
                            ->where('ranked.rn', '=', 1);
                    }
                )
                ->where('dependencies_resolved.dependable_id', $dependableId)
                ->where('dependencies_resolved.dependable_type', $dependableType)
                ->groupBy('dependencies.dependent_mod_id', 'dependencies.constraint')
                ->get();
        }

        return DB::table('dependencies')
            ->select('dependencies.dependent_mod_id', 'dependencies.constraint', 'ranked.id as latest_version_id')
            ->distinct()
            ->join('mods', function (JoinClause $join): void {
                $join->on('dependencies.dependent_mod_id', '=', 'mods.id')
                    ->whereNotNull('mods.published_at')
                    ->where('mods.published_at', '<=', now())
                    ->where('mods.disabled', false);
            })
            ->leftJoinSub(
                $this->rankedResolvedVersionsQuery($dependableId, $dependableType, $sptVersionId),
                'ranked',
                function (JoinClause $join): void {
                    $join->on('ranked.mod_id', '=', 'dependencies.dependent_mod_id')
                        ->where('ranked.rn', '=', 1);
                }
            )
            ->where('dependencies.dependable_id', $dependableId)
            ->where('dependencies.dependable_type', $dependableType)
            ->get();
    }

    /**
     * Build the subquery ranking the published resolved versions of each dependency mod for a dependable version,
     * newest first, optionally limited to versions compatible with an SPT version.
     *
     * @param  class-string  $dependableType
     */
    private function rankedResolvedVersionsQuery(int $dependableId, string $dependableType, ?int $sptVersionId): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('mod_versions as mv')
            ->select('mv.mod_id', 'mv.id')
            ->selectRaw('ROW_NUMBER() OVER (
                PARTITION BY mv.mod_id
                ORDER BY mv.version_major DESC, mv.version_minor DESC, mv.version_patch DESC,
                         CASE WHEN mv.version_labels = ? THEN 0 ELSE 1 END, mv.version_labels
            ) as rn', [''])
            ->join('dependencies_resolved as rd', 'mv.id', '=', 'rd.resolved_mod_version_id')
            ->where('rd.dependable_id', $dependableId)
            ->where('rd.dependable_type', $dependableType)
            ->whereNotNull('mv.published_at')
            ->where('mv.published_at', '<=', now())
            ->where('mv.disabled', false);

        if ($sptVersionId !== null) {
            $query->whereExists(function (\Illuminate\Database\Query\Builder $q) use ($sptVersionId): void {
                $q->select(DB::raw(1))
                    ->from('mod_version_spt_version')
                    ->whereColumn('mod_version_spt_version.mod_version_id', 'mv.id')
                    ->where('mod_version_spt_version.spt_version_id', $sptVersionId);
            });
        }

        return $query;
    }

    /**
     * Record the constraint of each dependency row against its dependent mod ID.
     *
     * @param  Collection<int, stdClass>  $dependencies
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId
     */
    private function recordConstraints(Collection $dependencies, Collection $constraintsByModId): void
    {
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
    }

    /**
     * Build the raw tree nodes for a set of dependency rows, recursing into each resolved version's own tree.
     *
     * @param  Collection<int, stdClass>  $dependencies
     * @param  Collection<int, int>  $processedVersionIds
     * @param  Collection<int, Collection<int, string>>  $constraintsByModId
     * @return array<int, array{mod: Mod, latest_version_id: int, latest_version: ModVersion|null, dependencies: array<int, mixed>}>
     */
    private function buildTreeNodes(Collection $dependencies, Collection $processedVersionIds, Collection $constraintsByModId, ?int $sptVersionId): array
    {
        $modIds = [];
        foreach ($dependencies as $dependency) {
            $modId = is_numeric($dependency->dependent_mod_id) ? (int) $dependency->dependent_mod_id : 0;
            if ($modId > 0 && ! in_array($modId, $modIds, true)) {
                $modIds[] = $modId;
            }
        }

        $versionIds = $this->latestVersionIdsFromRows($dependencies);
        $mods = $this->dependencyModsForModIds($modIds, $versionIds);

        // Build a map of mod_id => latest_version_id from our dependencies
        $modVersionMap = $dependencies->pluck('latest_version_id', 'dependent_mod_id');

        // Build tree nodes for each mod
        return $mods->map(function (Mod $mod) use ($modVersionMap, $processedVersionIds, $constraintsByModId, $sptVersionId): array {
            $rawVersionId = $modVersionMap[$mod->id] ?? null;
            $latestVersionId = is_numeric($rawVersionId) ? (int) $rawVersionId : 0;
            $latestVersion = $latestVersionId ? $mod->versions->firstWhere('id', $latestVersionId) : null;
            $latestVersion?->setRelation('mod', $mod);

            // Recursively build dependencies for this version
            $subDependencies = $latestVersionId
                ? $this->buildDependencyTree($latestVersionId, $processedVersionIds, $constraintsByModId, $sptVersionId)
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
     * Get the unique queried pair strings whose version and mod ID or GUID match the given resolved mod version row.
     *
     * @param  Collection<int, array{identifier: string, version: string, is_mod_id: bool}>  $modVersionPairs
     * @return list<string>
     */
    private function matchingModPairKeys(stdClass $row, Collection $modVersionPairs): array
    {
        $version = is_string($row->version) ? $row->version : '';
        $modId = is_numeric($row->mod_id) ? (int) $row->mod_id : 0;
        $guid = is_string($row->guid) ? Str::lower($row->guid) : null;

        return array_values($modVersionPairs
            ->filter(fn (array $pair): bool => $pair['version'] === $version
                && ($pair['is_mod_id']
                    ? (int) $pair['identifier'] === $modId
                    : Str::lower($pair['identifier']) === $guid))
            ->map(fn (array $pair): string => $pair['identifier'].':'.$pair['version'])
            ->unique()
            ->all());
    }

    /**
     * Get the unique queried pair strings whose version and addon ID or slug match the given resolved addon version
     * row.
     *
     * @param  Collection<int, array{identifier: string, version: string, is_addon_id: bool}>  $addonVersionPairs
     * @return list<string>
     */
    private function matchingAddonPairKeys(stdClass $row, Collection $addonVersionPairs): array
    {
        $version = is_string($row->version) ? $row->version : '';
        $addonId = is_numeric($row->addon_id) ? (int) $row->addon_id : 0;
        $slug = is_string($row->slug) ? $row->slug : null;

        return array_values($addonVersionPairs
            ->filter(fn (array $pair): bool => $pair['version'] === $version
                && ($pair['is_addon_id']
                    ? (int) $pair['identifier'] === $addonId
                    : $pair['identifier'] === $slug))
            ->map(fn (array $pair): string => $pair['identifier'].':'.$pair['version'])
            ->unique()
            ->all());
    }

    /**
     * Extract the unique, positive latest version IDs from resolved dependency rows.
     *
     * @param  Collection<int, stdClass>  $dependencies
     * @return list<int>
     */
    private function latestVersionIdsFromRows(Collection $dependencies): array
    {
        $versionIds = [];

        foreach ($dependencies as $dependency) {
            $latestVersionId = $dependency->latest_version_id;

            if (! is_numeric($latestVersionId)) {
                continue;
            }

            $latestVersionId = (int) $latestVersionId;

            if ($latestVersionId > 0 && ! in_array($latestVersionId, $versionIds, true)) {
                $versionIds[] = $latestVersionId;
            }
        }

        return $versionIds;
    }

    /**
     * Get the visible mods for the given dependency mod IDs with the given dependency versions eager loaded,
     * memoized per instance.
     *
     * @param  list<int>  $modIds
     * @param  list<int>  $versionIds
     * @return EloquentCollection<int, Mod>
     */
    private function dependencyModsForModIds(array $modIds, array $versionIds): EloquentCollection
    {
        return $this->dependencyModsByModIds[implode(',', $modIds).'|'.implode(',', $versionIds)] ??= (new ModDependencyTreeQueryBuilder)->apply()
            ->whereIn('mods.id', $modIds)
            ->with(['versions' => function (Relation $query) use ($versionIds): void {
                $query->whereIn('id', $versionIds);
            }])
            ->get();
    }

    /**
     * Build the base query for published, visible mod versions compatible with a specific SPT version, ordered newest
     * first.
     *
     * @return Builder<ModVersion>
     */
    private function publishedVersionsForSptQuery(string $sptVersion): Builder
    {
        return ModVersion::query()
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
            ->orderBy('version_labels');
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
