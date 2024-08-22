<?php

namespace App\Observers;

use App\Exceptions\CircularDependencyException;
use App\Models\ModDependency;
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
     *
     * @throws CircularDependencyException
     */
    public function saved(ModVersion $modVersion): void
    {
        $this->resolveDependencyVersion($modVersion);
        $this->sptVersionService->resolve($modVersion);
    }

    /**
     * Resolve the ModVersion's dependencies.
     *
     * @throws CircularDependencyException
     */
    private function resolveDependencyVersion(ModVersion $modVersion): void
    {
        $dependencies = ModDependency::where('resolved_version_id', $modVersion->id)->get();
        foreach ($dependencies as $dependency) {
            $this->dependencyVersionService->resolve($dependency->modVersion);
        }
    }

    /**
     * Handle the ModVersion "deleted" event.
     *
     * @throws CircularDependencyException
     */
    public function deleted(ModVersion $modVersion): void
    {
        $this->resolveDependencyVersion($modVersion);
    }
}
