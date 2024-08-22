<?php

namespace App\Observers;

use App\Exceptions\CircularDependencyException;
use App\Models\ModDependency;
use App\Models\ModVersion;
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
     *
     * @throws CircularDependencyException
     */
    public function saved(ModDependency $modDependency): void
    {
        $this->resolveDependencyVersion($modDependency);
    }

    /**
     * Resolve the ModDependency's dependencies.
     *
     * @throws CircularDependencyException
     */
    public function resolveDependencyVersion(ModDependency $modDependency): void
    {
        $modVersion = ModVersion::find($modDependency->mod_version_id);
        if ($modVersion) {
            $this->dependencyVersionService->resolve($modVersion);
        }
    }

    /**
     * Handle the ModDependency "deleted" event.
     *
     * @throws CircularDependencyException
     */
    public function deleted(ModDependency $modDependency): void
    {
        $this->resolveDependencyVersion($modDependency);
    }
}
