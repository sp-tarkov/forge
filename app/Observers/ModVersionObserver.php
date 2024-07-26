<?php

namespace App\Observers;

use App\Models\ModDependency;
use App\Models\ModVersion;
use App\Services\ModVersionService;

class ModVersionObserver
{
    protected ModVersionService $modVersionService;

    public function __construct(ModVersionService $modVersionService)
    {
        $this->modVersionService = $modVersionService;
    }

    public function saved(ModVersion $modVersion): void
    {
        $dependencies = ModDependency::where('resolved_version_id', $modVersion->id)->get();
        foreach ($dependencies as $dependency) {
            $this->modVersionService->resolveDependencies($dependency->modVersion);
        }
    }

    public function deleted(ModVersion $modVersion): void
    {
        $dependencies = ModDependency::where('resolved_version_id', $modVersion->id)->get();
        foreach ($dependencies as $dependency) {
            $this->modVersionService->resolveDependencies($dependency->modVersion);
        }
    }
}
