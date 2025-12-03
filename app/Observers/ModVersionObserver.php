<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Services\AddonVersionService;
use App\Services\DependencyVersionService;
use App\Services\SptVersionService;
use Illuminate\Database\Eloquent\Builder;

class ModVersionObserver
{
    public function __construct(
        protected DependencyVersionService $dependencyVersionService,
        protected SptVersionService $sptVersionService,
        protected AddonVersionService $addonVersionService,
    ) {}

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
     * Update properties on related SptVersions.
     */
    protected function updateRelatedSptVersions(ModVersion $modVersion): void
    {
        $sptVersions = $modVersion->sptVersions; // These should already be resolved.

        foreach ($sptVersions as $sptVersion) {
            $sptVersion->updateModCount();
        }
    }

    /**
     * Update properties on the related Mod.
     */
    protected function updateRelatedMod(ModVersion $modVersion): void
    {
        if ($modVersion->mod()->exists()) {
            $mod = $modVersion->mod;
            $mod->calculateDownloads();
        }
    }

    /**
     * Re-resolve addon versions that may be affected by this mod version change.
     *
     * When a mod version is created, updated, or deleted, addons for that mod may need their compatible version
     * constraints re-evaluated. For example, if an addon has constraint "~2.0.5" and the mod releases version 2.0.6,
     * the addon's resolved compatible versions should automatically include the new version.
     */
    protected function resolveRelatedAddonVersions(ModVersion $modVersion): void
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
