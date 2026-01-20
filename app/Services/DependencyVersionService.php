<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AddonVersion;
use App\Models\ModVersion;
use Composer\Semver\Semver;

class DependencyVersionService
{
    /**
     * Resolve the dependencies for a mod version or addon version.
     */
    public function resolve(ModVersion|AddonVersion $dependable): void
    {
        // Refresh the dependencies relationship to get the latest state
        $dependable->load('dependencies');

        $dependencies = $this->satisfyConstraint($dependable);
        $dependable->dependenciesResolved()->sync($dependencies);
    }

    /**
     * Satisfies all dependency constraints of a ModVersion or AddonVersion.
     *
     * @return array<int, array<string, int>>
     */
    private function satisfyConstraint(ModVersion|AddonVersion $dependable): array
    {
        // Eager-load the dependencies and their mod versions if not already loaded.
        if (! $dependable->relationLoaded('dependencies')) {
            $dependable->load('dependencies.dependentMod.versions');
        }

        // Iterate over each dependency.
        $dependencies = [];
        foreach ($dependable->dependencies as $dependency) {
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
