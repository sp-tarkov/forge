<?php

namespace App\Services;

use App\Models\ModVersion;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\Log;

class ModVersionService
{
    // TODO: This works, but it needs to be refactored. It's too big and does too much.
    public function resolveDependencies(ModVersion $modVersion): array
    {
        $resolvedVersions = [];

        try {
            // Eager load dependencies with related mod versions
            $dependencies = $modVersion->dependencies()->with(['dependencyMod.versions'])->get();

            foreach ($dependencies as $dependency) {
                $dependencyMod = $dependency->dependencyMod;

                // Ensure dependencyMod exists and has versions
                if (! $dependencyMod || $dependencyMod->versions->isEmpty()) {
                    if ($dependency->resolved_version_id !== null) {
                        $dependency->updateQuietly(['resolved_version_id' => null]);
                    }
                    $resolvedVersions[$dependency->id] = null;

                    continue;
                }

                // Get available versions in the form ['version' => 'id']
                $availableVersions = $dependencyMod->versions->pluck('id', 'version')->toArray();

                // Find the latest version that satisfies the constraint
                $satisfyingVersions = Semver::satisfiedBy(array_keys($availableVersions), $dependency->version_constraint);

                // Get the first element's id from satisfyingVersions
                $latestVersionId = $satisfyingVersions ? $availableVersions[reset($satisfyingVersions)] : null;

                // Update the resolved version ID in the ModDependency record
                if ($dependency->resolved_version_id !== $latestVersionId) {
                    $dependency->updateQuietly(['resolved_version_id' => $latestVersionId]);
                }

                // Add the resolved ModVersion to the array (or null if not found)
                $resolvedVersions[$dependency->id] = $latestVersionId ? ModVersion::find($latestVersionId) : null;
            }
        } catch (\Exception $e) {
            Log::error('Error resolving dependencies for ModVersion: '.$modVersion->id, [
                'exception' => $e->getMessage(),
                'mod_version_id' => $modVersion->id,
            ]);
        }

        return $resolvedVersions;
    }
}
