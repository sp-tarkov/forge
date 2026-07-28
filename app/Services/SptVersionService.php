<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Support\VersionMatcher;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SptVersionService
{
    /**
     * Resolve dependencies for the given mod version.
     */
    public function resolve(ModVersion $modVersion): void
    {
        $satisfyingVersionIds = $this->satisfyConstraint($modVersion->spt_version_constraint, $this->getAvailableVersions());

        // Preserve existing pivot data (like pinned_to_spt_publish) when syncing
        $pivotData = [];
        foreach ($satisfyingVersionIds as $versionId) {
            $pivotData[$versionId] = ['pinned_to_spt_publish' => false];
        }

        // Preserve any existing pinned_to_spt_publish values
        $existingPivots = $modVersion->sptVersions()
            ->whereIn('spt_version_id', $satisfyingVersionIds)
            ->get()
            ->pluck('pivot');

        foreach ($existingPivots as $pivot) {
            /** @var object{pinned_to_spt_publish: bool, spt_version_id: int} $pivot */
            if ($pivot->pinned_to_spt_publish) {
                $pivotData[$pivot->spt_version_id]['pinned_to_spt_publish'] = true;
            }
        }

        $modVersion->sptVersions()->sync($pivotData);
    }

    /**
     * Resolve the SPT versions for every mod version using batched reads and writes, caching the satisfying version
     * IDs per distinct constraint and reconciling the pivot table with diffed bulk inserts and deletes.
     */
    public function resolveAll(): void
    {
        $availableVersions = $this->getAvailableVersions();

        /** @var array<string, array<int>> $satisfyingIdsByConstraint */
        $satisfyingIdsByConstraint = [];

        ModVersion::query()
            ->select(['id', 'spt_version_constraint'])
            ->chunkById(500, function (EloquentCollection $modVersions) use ($availableVersions, &$satisfyingIdsByConstraint): void {
                $desired = [];

                /** @var ModVersion $modVersion */
                foreach ($modVersions as $modVersion) {
                    $constraint = $modVersion->spt_version_constraint;
                    $satisfyingIdsByConstraint[$constraint] ??= $this->satisfyConstraint($constraint, $availableVersions);
                    $desired[$modVersion->id] = $satisfyingIdsByConstraint[$constraint];
                }

                $this->reconcilePivots($desired);
            });
    }

    /**
     * Reconcile the mod_version_spt_version pivot table with the desired state: bulk-insert missing rows and delete
     * stale rows, leaving matching rows (and their pinned_to_spt_publish values) untouched.
     *
     * @param  array<int, array<int>>  $desired  Satisfying SPT version IDs keyed by mod version ID.
     */
    private function reconcilePivots(array $desired): void
    {
        $existingByModVersion = [];
        $existingRows = DB::table('mod_version_spt_version')
            ->whereIn('mod_version_id', array_keys($desired))
            ->get(['mod_version_id', 'spt_version_id']);

        foreach ($existingRows as $row) {
            /** @var object{mod_version_id: int, spt_version_id: int} $row */
            $existingByModVersion[$row->mod_version_id][] = $row->spt_version_id;
        }

        $now = now();
        $inserts = [];
        $staleByModVersion = [];

        foreach ($desired as $modVersionId => $sptVersionIds) {
            $existing = $existingByModVersion[$modVersionId] ?? [];

            foreach (array_diff($sptVersionIds, $existing) as $sptVersionId) {
                $inserts[] = [
                    'mod_version_id' => $modVersionId,
                    'spt_version_id' => $sptVersionId,
                    'pinned_to_spt_publish' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $stale = array_diff($existing, $sptVersionIds);
            if ($stale !== []) {
                $staleByModVersion[$modVersionId] = array_values($stale);
            }
        }

        foreach (array_chunk($inserts, 500) as $insertChunk) {
            DB::table('mod_version_spt_version')->insertOrIgnore($insertChunk);
        }

        if ($staleByModVersion !== []) {
            DB::table('mod_version_spt_version')
                ->where(function (Builder $query) use ($staleByModVersion): void {
                    foreach ($staleByModVersion as $modVersionId => $sptVersionIds) {
                        $query->orWhere(fn (Builder $subQuery): Builder => $subQuery
                            ->where('mod_version_id', $modVersionId)
                            ->whereIn('spt_version_id', $sptVersionIds));
                    }
                })
                ->delete();
        }
    }

    /**
     * Satisfies the given version constraint. Returns the IDs of the satisfying SptVersions.
     *
     * @param  Collection<string, int>  $availableVersions
     * @return array<int>
     */
    private function satisfyConstraint(string $constraint, Collection $availableVersions): array
    {
        return match ($constraint) {
            '' => [],
            default => $this->resolveSemverConstraint($constraint, $availableVersions),
        };
    }

    /**
     * Resolve a SemVer constraint to matching version IDs.
     *
     * When a constraint doesn't match any SPT versions, returns an empty array.
     * Mod versions with unresolvable constraints will show "Unknown SPT Version" on the front-end.
     *
     * @param  Collection<string, int>  $availableVersions
     * @return array<int, int>
     */
    private function resolveSemverConstraint(string $constraint, Collection $availableVersions): array
    {
        $satisfyingVersions = VersionMatcher::satisfiedBy($availableVersions->keys()->all(), $constraint);

        return collect($satisfyingVersions)
            ->map(fn (string $version): ?int => $availableVersions[$version] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get all available SPT versions as a collection.
     *
     * @return Collection<string, int>
     */
    private function getAvailableVersions(): Collection
    {
        /** @var Collection<string, int> */
        return SptVersion::query()
            ->orderBy('version', 'desc')
            ->pluck('id', 'version');
    }
}
