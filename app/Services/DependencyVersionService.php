<?php

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
        $dependencies = $this->satisfyConstraint($modVersion);
        $modVersion->resolvedDependencies()->sync($dependencies);
    }

    /**
     * Satisfies all dependency constraints of a ModVersion.
     */
    private function satisfyConstraint(ModVersion $modVersion): array
    {
        // Eager load the dependencies and their mod versions.
        $modVersion->load('dependencies.dependentMod.versions');

        // Iterate over each ModVersion dependency.
        $dependencies = [];
        foreach ($modVersion->dependencies as $dependency) {

            // Get all dependent mod versions.
            $dependentModVersions = $dependency->dependentMod->versions()->get();

            // Filter the dependent mod versions to find the ones that satisfy the dependency constraint.
            $matchedVersions = $dependentModVersions->filter(function ($version) use ($dependency) {
                return Semver::satisfies($version->version, $dependency->constraint);
            });

            // Map the matched versions to the sync data.
            foreach ($matchedVersions as $matchedVersion) {
                $dependencies[$matchedVersion->id] = ['dependency_id' => $dependency->id];
            }
        }

        return $dependencies;
    }
}
