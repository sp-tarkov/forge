<?php

namespace App\Services;

use App\Exceptions\CircularDependencyException;
use App\Models\ModDependency;
use App\Models\ModVersion;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Collection;

class ModVersionService
{
    protected array $visited = [];

    protected array $stack = [];

    /**
     * Resolve dependencies for the given mod version.
     *
     * @throws CircularDependencyException
     */
    public function resolveDependencies(ModVersion $modVersion): array
    {
        $resolvedVersions = [];
        $this->visited = [];
        $this->stack = [];

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
        if (in_array($modVersion->id, $this->stack)) {
            throw new CircularDependencyException("Circular dependency detected in ModVersion ID: {$modVersion->id}");
        }

        if (in_array($modVersion->id, $this->visited)) {
            return; // Skip already processed versions
        }

        $this->visited[] = $modVersion->id;
        $this->stack[] = $modVersion->id;

        /** @var Collection|ModDependency[] $dependencies */
        $dependencies = $this->getDependencies($modVersion);

        foreach ($dependencies as $dependency) {
            $resolvedVersionId = $this->resolveVersionIdForDependency($dependency);

            if ($dependency->resolved_version_id !== $resolvedVersionId) {
                $dependency->updateQuietly(['resolved_version_id' => $resolvedVersionId]);
            }

            $resolvedVersions[$dependency->id] = $resolvedVersionId ? ModVersion::find($resolvedVersionId) : null;

            if ($resolvedVersionId) {
                $nextModVersion = ModVersion::find($resolvedVersionId);
                if ($nextModVersion) {
                    $this->processDependencies($nextModVersion, $resolvedVersions);
                }
            }
        }

        array_pop($this->stack);
    }

    /**
     * Get the dependencies for the given mod version.
     */
    protected function getDependencies(ModVersion $modVersion): Collection
    {
        return $modVersion->dependencies()->with(['dependencyMod.versions'])->get();
    }

    /**
     * Resolve the latest version ID that satisfies the version constraint on given dependency.
     */
    protected function resolveVersionIdForDependency(ModDependency $dependency): ?int
    {
        $mod = $dependency->dependencyMod;

        if (! $mod || $mod->versions->isEmpty()) {
            return null;
        }

        $availableVersions = $mod->versions->pluck('id', 'version')->toArray();
        $satisfyingVersions = Semver::satisfiedBy(array_keys($availableVersions), $dependency->version_constraint);

        // Versions are sorted in descending order by default. Take the first key (the latest version) using `reset()`.
        return $satisfyingVersions ? $availableVersions[reset($satisfyingVersions)] : null;
    }
}
