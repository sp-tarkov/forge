<?php

namespace App\Observers;

use App\Models\ModVersion;
use App\Services\DependencyVersionService;
use App\Services\SptVersionService;

class ModVersionObserver
{
    protected DependencyVersionService $dependencyVersionService;

    protected SptVersionService $sptVersionService;

    public function __construct(
        DependencyVersionService $dependencyVersionService,
        SptVersionService $sptVersionService,
    ) {
        $this->dependencyVersionService = $dependencyVersionService;
        $this->sptVersionService = $sptVersionService;
    }

    /**
     * Handle the ModVersion "saved" event.
     */
    public function saved(ModVersion $modVersion): void
    {
        $this->dependencyVersionService->resolve($modVersion);

        $this->sptVersionService->resolve($modVersion);

        $this->updateRelatedSptVersions($modVersion); // Always done after resolving SPT versions.
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
     * Handle the ModVersion "deleted" event.
     */
    public function deleted(ModVersion $modVersion): void
    {
        $this->dependencyVersionService->resolve($modVersion);

        $this->updateRelatedSptVersions($modVersion); // Always done after resolving SPT versions.
    }
}
