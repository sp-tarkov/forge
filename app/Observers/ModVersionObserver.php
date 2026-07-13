<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\DependencyResolver;
use App\Enums\VerificationTrigger;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use App\Services\AddonVersionService;
use App\Services\SptVersionService;
use Illuminate\Database\Eloquent\Builder;

final readonly class ModVersionObserver
{
    public function __construct(
        private DependencyResolver $dependencyVersionService,
        private SptVersionService $sptVersionService,
        private AddonVersionService $addonVersionService,
    ) {}

    /**
     * Handle the ModVersion "created" event.
     */
    public function created(ModVersion $modVersion): void
    {
        $this->dispatchVerification($modVersion);
    }

    /**
     * Handle the ModVersion "saved" event.
     */
    public function saved(ModVersion $modVersion): void
    {
        $this->dependencyVersionService->resolve($modVersion);

        $this->sptVersionService->resolve($modVersion);

        $this->updateRelatedSptVersions($modVersion); // After resolving SPT versions.
        $this->updateRelatedMod($modVersion);
        $this->resolveRelatedAddonVersions($modVersion);
    }

    /**
     * Handle the ModVersion "deleted" event.
     */
    public function deleted(ModVersion $modVersion): void
    {
        $this->dependencyVersionService->resolve($modVersion);

        $this->updateRelatedSptVersions($modVersion); // After resolving SPT versions.
        $this->updateRelatedMod($modVersion);
        $this->resolveRelatedAddonVersions($modVersion);
    }

    /**
     * Dispatch a file verification for a newly uploaded version when the pipeline is enabled and the version has a
     * downloadable link and is not disabled.
     */
    private function dispatchVerification(ModVersion $modVersion): void
    {
        if (! config()->boolean('verification.enabled')) {
            return;
        }

        if ($modVersion->link === '' || $modVersion->disabled) {
            return;
        }

        VerificationResult::dispatchFor($modVersion, VerificationTrigger::Upload);
    }

    /**
     * Update properties on related SptVersions.
     */
    private function updateRelatedSptVersions(ModVersion $modVersion): void
    {
        $sptVersions = $modVersion->sptVersions; // These should already be resolved.

        foreach ($sptVersions as $sptVersion) {
            $sptVersion->updateModCount();
        }
    }

    /**
     * Update properties on the related Mod.
     */
    private function updateRelatedMod(ModVersion $modVersion): void
    {
        /** @var Mod|null $mod */
        $mod = $modVersion->mod;
        $mod?->fresh()?->calculateDownloads();
    }

    /**
     * Re-resolve addon versions that may be affected by this mod version change.
     *
     * When a mod version is created, updated, or deleted, addons for that mod may need their compatible version
     * constraints re-evaluated. For example, if an addon has constraint "~2.0.5" and the mod releases version 2.0.6,
     * the addon's resolved compatible versions should automatically include the new version.
     */
    private function resolveRelatedAddonVersions(ModVersion $modVersion): void
    {
        if (! $modVersion->mod_id) {
            return;
        }

        // Find all addon versions for addons that belong to this mod
        $addonVersions = AddonVersion::query()
            ->whereHas('addon', fn (Builder $query): Builder => $query->where('mod_id', $modVersion->mod_id))
            ->get();

        foreach ($addonVersions as $addonVersion) {
            $this->addonVersionService->resolve($addonVersion);
        }
    }
}
