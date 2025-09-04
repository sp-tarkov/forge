<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ModVersion;
use App\Models\SptVersion;
use Composer\Semver\Semver;

class SptVersionService
{
    /**
     * Resolve dependencies for the given mod version.
     */
    public function resolve(ModVersion $modVersion): void
    {
        $satisfyingVersionIds = $this->satisfyConstraint($modVersion);
        $modVersion->sptVersions()->sync($satisfyingVersionIds);
    }

    /**
     * Satisfies the version constraint of a given ModVersion. Returns the ID of the satisfying SptVersion.
     *
     * @return array<int>
     */
    private function satisfyConstraint(ModVersion $modVersion): array
    {
        if ($modVersion->spt_version_constraint === '' || $modVersion->spt_version_constraint === '0.0.0') {
            return [];
        }

        $availableVersions = SptVersion::query()
            ->orderBy('version', 'desc')
            ->pluck('id', 'version')
            ->toArray();

        // Attempt to satisfy the SemVer constraint with the available SPT versions
        $satisfyingVersions = Semver::satisfiedBy(array_keys($availableVersions), $modVersion->spt_version_constraint);

        if (empty($satisfyingVersions)) {
            // Check if this is an outdated constraint (for SPT versions that no longer exist) by checking if the
            // constraint is for a version lower than our minimum SPT version
            $minAvailableVersion = min(array_filter(array_keys($availableVersions), fn ($v): bool => $v !== '0.0.0'));

            if (isset($availableVersions['0.0.0'])) {
                return [$availableVersions['0.0.0']]; // Constraint for a legacy mod
            }

            return []; // Invalid constraint
        }

        // Return the IDs of all satisfying versions
        return array_map(fn (string $version): int => $availableVersions[$version], $satisfyingVersions);
    }
}
