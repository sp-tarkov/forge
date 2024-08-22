<?php

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
        $modVersion->resolved_spt_version_id = $this->satisfyconstraint($modVersion);
        $modVersion->saveQuietly();
    }

    /**
     * Satisfies the version constraint of a given ModVersion. Returns the ID of the satisfying SptVersion.
     */
    private function satisfyConstraint(ModVersion $modVersion): ?int
    {
        $availableVersions = SptVersion::query()
            ->orderBy('version', 'desc')
            ->pluck('id', 'version')
            ->toArray();

        $satisfyingVersions = Semver::satisfiedBy(array_keys($availableVersions), $modVersion->spt_version_constraint);
        if (empty($satisfyingVersions)) {
            return null;
        }

        // Ensure the satisfying versions are sorted in descending order to get the latest version
        usort($satisfyingVersions, 'version_compare');
        $satisfyingVersions = array_reverse($satisfyingVersions);

        // Return the ID of the latest satisfying version
        return $availableVersions[$satisfyingVersions[0]];
    }
}
