<?php

namespace App\Observers;

use App\Models\Mod;
use App\Services\DependencyVersionService;

class ModObserver
{
    protected DependencyVersionService $dependencyVersionService;

    public function __construct(
        DependencyVersionService $dependencyVersionService,
    ) {
        $this->dependencyVersionService = $dependencyVersionService;
    }

    /**
     * Handle the Mod "saved" event.
     */
    public function saved(Mod $mod): void
    {
        $mod->load('versions.sptVersions');

        foreach ($mod->versions as $modVersion) {
            $this->dependencyVersionService->resolve($modVersion);
        }

        $this->updateRelatedSptVersions($mod);
    }

    /**
     * Update properties on related SptVersions.
     */
    protected function updateRelatedSptVersions(Mod $mod): void
    {
        $sptVersions = $mod->versions->flatMap->sptVersions->unique();

        foreach ($sptVersions as $sptVersion) {
            $sptVersion->updateModCount();
        }
    }

    /**
     * Handle the Mod "deleted" event.
     */
    public function deleted(Mod $mod): void
    {
        $mod->load('versions.sptVersions');

        $this->updateRelatedSptVersions($mod);
    }
}
