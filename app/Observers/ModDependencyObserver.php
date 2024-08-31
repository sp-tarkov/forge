<?php

namespace App\Observers;

use App\Models\ModDependency;
use App\Services\DependencyVersionService;

class ModDependencyObserver
{
    protected DependencyVersionService $dependencyVersionService;

    public function __construct(DependencyVersionService $dependencyVersionService)
    {
        $this->dependencyVersionService = $dependencyVersionService;
    }

    /**
     * Handle the ModDependency "saved" event.
     */
    public function saved(ModDependency $modDependency): void
    {
        $this->dependencyVersionService->resolve($modDependency->modVersion);
    }

    /**
     * Handle the ModDependency "deleted" event.
     */
    public function deleted(ModDependency $modDependency): void
    {
        $this->dependencyVersionService->resolve($modDependency->modVersion);
    }
}
