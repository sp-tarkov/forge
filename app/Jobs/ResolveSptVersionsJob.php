<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ModVersion;
use App\Services\SptVersionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

class ResolveSptVersionsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Resolve the SPT versions for each of the mod versions.
     */
    public function handle(): void
    {
        $sptVersionService = new SptVersionService;

        ModVersion::query()
            ->chunk(100, function (Collection $modVersions) use ($sptVersionService): void {
                foreach ($modVersions as $modVersion) {
                    $sptVersionService->resolve($modVersion);
                }
            });
    }
}
