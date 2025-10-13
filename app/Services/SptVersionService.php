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
        $modVersion->sptVersions()->sync($satisfyingVersionIds);
    }

    /**
     * Satisfies the version constraint of a given ModVersion. Returns the ID of the satisfying SptVersion.
     *
     * @return array<int>
     */
    private function satisfyConstraint(ModVersion $modVersion): array
    {
        return match ($modVersion->spt_version_constraint) {
            '' => [],
            '0.0.0' => $this->getLegacyVersionId(),
            default => $this->resolveSemverConstraint($modVersion->spt_version_constraint),
        };
    }

    /**
     * Get the ID of the legacy 0.0.0 version if it exists.
     *
     * @return array<int>
     */
    private function getLegacyVersionId(): array
    {
        return SptVersion::query()
            ->where('version', '0.0.0')
            ->pluck('id')
            ->toArray();
    }

    /**
     * Resolve a SemVer constraint to matching version IDs.
     *
     * @return array<int, int>
     */
    private function resolveSemverConstraint(string $constraint): array
    {
        $availableVersions = $this->getAvailableVersions();
        $satisfyingVersions = Semver::satisfiedBy($availableVersions->keys()->all(), $constraint);

        return collect($satisfyingVersions)
            ->whenEmpty(fn (Collection $collection): Collection => $this->handleLegacyFallback($availableVersions))
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

    /**
     * Handle legacy constraint fallback when no satisfying versions are found.
     *
     * @param  Collection<string, int>  $availableVersions
     * @return Collection<int, string>
     */
    private function handleLegacyFallback(Collection $availableVersions): Collection
    {
        return $availableVersions->has('0.0.0')
            ? collect(['0.0.0'])
            : collect([]);
    }
}
