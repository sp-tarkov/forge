<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ModVersion;
use App\Services\DependencyVersionService;
use App\Services\SptVersionService;

class ModVersionObserver
{
    public function __construct(protected DependencyVersionService $dependencyVersionService, protected SptVersionService $sptVersionService) {}

    /**
     * Handle the ModVersion "saved" event.
     */
    public function saved(ModVersion $modVersion): void
    {
        $this->dependencyVersionService->resolve($modVersion);

        $this->sptVersionService->resolve($modVersion);

        $this->updateRelatedSptVersions($modVersion); // After resolving SPT versions.
        $this->updateRelatedMod($modVersion);
    }

    /**
     * Handle the ModVersion "deleted" event.
     */
    public function deleted(ModVersion $modVersion): void
    {
        $this->dependencyVersionService->resolve($modVersion);

        $this->updateRelatedSptVersions($modVersion); // After resolving SPT versions.
        $this->updateRelatedMod($modVersion);
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
}
