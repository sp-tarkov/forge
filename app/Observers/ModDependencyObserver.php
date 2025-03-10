<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ModDependency;
use App\Services\DependencyVersionService;

class ModDependencyObserver
{
    public function __construct(protected DependencyVersionService $dependencyVersionService) {}

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
