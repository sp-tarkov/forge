<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ModVersion;
use App\Services\SptVersionService;
use Illuminate\Support\Facades\Cache;

class SptVersionObserver
{
    public function __construct(protected SptVersionService $sptVersionService) {}

    /**
     * Handle the SptVersion "saved" event.
     */
    public function saved(): void
    {
        // Clear all SPT version caches
        $this->clearSptVersionCaches();

        defer(fn () => $this->resolveSptVersion());
    }

    /**
     * Handle the SptVersion "deleted" event.
     */
    public function deleted(): void
    {
        // Clear all SPT version caches
        $this->clearSptVersionCaches();

        defer(fn () => $this->resolveSptVersion());
    }

    /**
     * Clear SPT version caches.
     */
    private function clearSptVersionCaches(): void
    {
        // Clear all SPT version caches using the new naming convention
        Cache::forget('spt-versions:all:user');
        Cache::forget('spt-versions:all:additional-authors');
        Cache::forget('spt-versions:active:user');
        Cache::forget('spt-versions:active:admin');
        Cache::forget('spt-versions:filter-list:user');
        Cache::forget('spt-versions:filter-list:admin');

        // Clear mod categories cache as well
        Cache::forget('mod-categories');
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
}
