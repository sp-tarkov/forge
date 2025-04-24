<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Mod;
use App\Models\SptVersion;
use App\Services\DependencyVersionService;
use Illuminate\Support\Collection;

class ModObserver
{
    public function __construct(protected DependencyVersionService $dependencyVersionService) {}

    /**
     * Handle the Mod "saved" event.
     */
    public function saved(Mod $mod): void
    {
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
        /** @var Collection<int, SptVersion> $sptVersions */
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
        $this->updateRelatedSptVersions($mod);
    }
}
