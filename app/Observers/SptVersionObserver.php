<?php

namespace App\Observers;

use App\Models\ModVersion;
use App\Services\SptVersionService;

class SptVersionObserver
{
    protected SptVersionService $sptVersionService;

    public function __construct(SptVersionService $sptVersionService)
    {
        $this->sptVersionService = $sptVersionService;
    }

    /**
     * Handle the SptVersion "saved" event.
     */
    public function saved(): void
    {
        $this->resolveSptVersion();
    }

    /**
     * Resolve the SptVersion's dependencies.
     */
    private function resolveSptVersion(): void
    {
        $modVersions = ModVersion::all();

        foreach ($modVersions as $modVersion) {
            $this->sptVersionService->resolve($modVersion);
        }
    }

    /**
     * Handle the SptVersion "deleted" event.
     */
    public function deleted(): void
    {
        $this->resolveSptVersion();
    }
}
