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
        $availableVersions = SptVersion::query()
            ->orderBy('version', 'desc')
            ->pluck('id', 'version')
            ->toArray();

        $satisfyingVersions = Semver::satisfiedBy(array_keys($availableVersions), $modVersion->spt_version_constraint);
        if (empty($satisfyingVersions)) {
            return [];
        }

        // Return the IDs of all satisfying versions
        return array_map(fn ($version) => $availableVersions[$version], $satisfyingVersions);
    }
}
