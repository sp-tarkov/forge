<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\DependencyResolver;
use App\Models\Dependency;

final readonly class DependencyObserver
{
    public function __construct(private DependencyResolver $dependencyVersionService) {}

    /**
     * Handle the Dependency "saved" event.
     */
    public function saved(Dependency $dependency): void
    {
        $this->dependencyVersionService->resolve($dependency->dependable);
    }

    /**
     * Handle the Dependency "deleted" event.
     */
    public function deleted(Dependency $dependency): void
    {
        $this->dependencyVersionService->resolve($dependency->dependable);
    }
}
