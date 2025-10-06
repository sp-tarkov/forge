<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ModVersion;
use App\Services\SptVersionService;

class SptVersionObserver
{
    public function __construct(protected SptVersionService $sptVersionService) {}

    /**
     * Handle the SptVersion "saved" event.
     */
    public function saved(): void
    {
        defer(fn () => $this->resolveSptVersion());
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
        defer(fn () => $this->resolveSptVersion());
    }
}
