<?php

namespace App\Observers;

use App\Models\ModDependency;
use App\Models\ModVersion;
use App\Services\ModVersionService;

class ModDependencyObserver
{
    protected ModVersionService $modVersionService;

    public function __construct(ModVersionService $modVersionService)
    {
        $this->modVersionService = $modVersionService;
    }

    public function saved(ModDependency $modDependency): void
    {
        $modVersion = ModVersion::find($modDependency->mod_version_id);
        if ($modVersion) {
            $this->modVersionService->resolveDependencies($modVersion);
        }
    }

    public function deleted(ModDependency $modDependency): void
    {
        $modVersion = ModVersion::find($modDependency->mod_version_id);
        if ($modVersion) {
            $this->modVersionService->resolveDependencies($modVersion);
        }
    }
}
