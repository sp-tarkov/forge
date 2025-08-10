<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ModVersion;
use Composer\Semver\Semver;

class DependencyVersionService
{
    /**
     * Resolve the dependencies for a mod version.
     */
    public function resolve(ModVersion $modVersion): void
    {
        // Refresh the dependencies relationship to get the latest state
        $modVersion->load('dependencies');

        $dependencies = $this->satisfyConstraint($modVersion);
        $modVersion->resolvedDependencies()->sync($dependencies);
    }

    /**
     * Satisfies all dependency constraints of a ModVersion.
     *
     * @return array<int, array<string, int>>
     */
    private function satisfyConstraint(ModVersion $modVersion): array
    {

        // Eager-load the dependencies and their mod versions if not already loaded.
        if (! $modVersion->relationLoaded('dependencies')) {
            $modVersion->load('dependencies.dependentMod.versions');
        }

        // Iterate over each ModVersion dependency.
        $dependencies = [];
        foreach ($modVersion->dependencies as $dependency) {
            // Skip if the dependency is being deleted or doesn't exist
            if (! $dependency->exists || ! $dependency->id) {
                continue;
            }

            // Get all dependent mod versions (use loaded relation if available).
            $dependentModVersions = $dependency->dependentMod->relationLoaded('versions')
                ? $dependency->dependentMod->versions
                : $dependency->dependentMod->versions()->get();

            // Filter the dependent mod versions to find the ones that satisfy the dependency constraint.
            $matchedVersions = $dependentModVersions->filter(fn (ModVersion $version): bool => Semver::satisfies($version->version, $dependency->constraint));

            // Map the matched versions to the sync data.
            foreach ($matchedVersions as $matchedVersion) {
                $dependencies[$matchedVersion->id] = ['dependency_id' => $dependency->id];
            }
        }

        return $dependencies;
    }
}
