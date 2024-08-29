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
        foreach ($mod->versions as $modVersion) {
            $this->dependencyVersionService->resolve($modVersion);
        }
    }
}
