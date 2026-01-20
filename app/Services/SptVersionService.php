<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ModVersion;
use App\Models\SptVersion;
use Composer\Semver\Semver;
use Illuminate\Support\Collection;

class SptVersionService
{
    /**
     * Resolve dependencies for the given mod version.
     */
    public function resolve(ModVersion $modVersion): void
    {
        $satisfyingVersionIds = $this->satisfyConstraint($modVersion);

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
            if ($pivot->pinned_to_spt_publish) {
                $pivotData[$pivot->spt_version_id]['pinned_to_spt_publish'] = true;
            }
        }

        $modVersion->sptVersions()->sync($pivotData);
    }

    /**
     * Satisfies the version constraint of a given ModVersion. Returns the IDs of the satisfying SptVersions.
     *
     * @return array<int>
     */
    private function satisfyConstraint(ModVersion $modVersion): array
    {
        return match ($modVersion->spt_version_constraint) {
            null, '' => [],
            default => $this->resolveSemverConstraint($modVersion->spt_version_constraint),
        };
    }

    /**
     * Resolve a SemVer constraint to matching version IDs.
     *
     * When a constraint doesn't match any SPT versions, returns an empty array.
     * Mod versions with unresolvable constraints will show "Unknown SPT Version" on the front-end.
     *
     * @return array<int, int>
     */
    private function resolveSemverConstraint(string $constraint): array
    {
        $availableVersions = $this->getAvailableVersions();
        $satisfyingVersions = Semver::satisfiedBy($availableVersions->keys()->all(), $constraint);

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
        return SptVersion::query()
            ->orderBy('version', 'desc')
            ->pluck('id', 'version');
    }
}
