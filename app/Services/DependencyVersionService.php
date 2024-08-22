<?php

namespace App\Services;

use App\Exceptions\CircularDependencyException;
use App\Models\ModDependency;
use App\Models\ModVersion;
use Composer\Semver\Semver;

class DependencyVersionService
{
    /**
     * Keep track of visited versions to avoid resolving them again.
     */
    protected array $visited = [];

    /**
     * Keep track of the current path in the depth-first search.
     */
    protected array $stack = [];

    /**
     * Resolve dependencies for the given mod version.
     *
     * @throws CircularDependencyException
     */
    public function resolve(ModVersion $modVersion): array
    {
        $this->visited = [];
        $this->stack = [];

        // Store the resolved versions for each dependency.
        $resolvedVersions = [];

        // Start the recursive depth-first search to resolve dependencies.
        $this->processDependencies($modVersion, $resolvedVersions);

        return $resolvedVersions;
    }

    /**
     * Perform a depth-first search to resolve dependencies for the given mod version.
     *
     * @throws CircularDependencyException
     */
    protected function processDependencies(ModVersion $modVersion, array &$resolvedVersions): void
    {
        // Detect circular dependencies
        if (in_array($modVersion->id, $this->stack)) {
            throw new CircularDependencyException("Circular dependency detected in ModVersion ID: {$modVersion->id}");
        }

        // Skip already processed versions
        if (in_array($modVersion->id, $this->visited)) {
            return;
        }

        // Mark the current version
        $this->visited[] = $modVersion->id;
        $this->stack[] = $modVersion->id;

        // Get the dependencies for the current mod version.
        $dependencies = $modVersion->dependencies(resolvedOnly: false)->get();

        foreach ($dependencies as $dependency) {
            // Resolve the latest mod version ID that satisfies the version constraint on the mod version dependency.
            $resolvedId = $this->resolveDependency($dependency);

            // Update the resolved version ID for the dependency if it has changed.
            // Do it "quietly" to avoid triggering the observer again.
            if ($dependency->resolved_version_id !== $resolvedId) {
                $dependency->updateQuietly(['resolved_version_id' => $resolvedId]);
            }

            // At this point, the dependency has been resolved (or not) and we can add it to the resolved versions to
            // avoid resolving it again in the future and to help with circular dependency detection.
            $resolvedVersions[$dependency->id] = $resolvedId ? ModVersion::find($resolvedId) : null;

            // Recursively process the resolved dependency.
            if ($resolvedId) {
                $nextModVersion = ModVersion::find($resolvedId);
                if ($nextModVersion) {
                    $this->processDependencies($nextModVersion, $resolvedVersions);
                }
            }
        }

        // Remove the current version from the stack now that we have processed all its dependencies.
        array_pop($this->stack);
    }

    /**
     * Resolve the latest mod version ID that satisfies the version constraint on the mod version dependency.
     */
    protected function resolveDependency(ModDependency $dependency): ?int
    {
        $dependencyModVersions = $dependency->dependencyMod->versions(resolvedOnly: false);

        // There are no mod versions for the dependency mod.
        if ($dependencyModVersions->doesntExist()) {
            return null;
        }

        $availableVersions = $dependencyModVersions->pluck('id', 'version')->toArray();
        $satisfyingVersions = Semver::satisfiedBy(array_keys($availableVersions), $dependency->version_constraint);

        // There are no mod versions that satisfy the version constraint.
        if (empty($satisfyingVersions)) {
            return null;
        }

        // Sort the satisfying versions in descending order using the version_compare function.
        usort($satisfyingVersions, 'version_compare');
        $satisfyingVersions = array_reverse($satisfyingVersions);

        // Return the latest (highest version number) satisfying version.
        return $availableVersions[$satisfyingVersions[0]];
    }
}
