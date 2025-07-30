<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ModVersion;
use App\Services\SptVersionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResolveSptVersionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Resolve the SPT versions for each of the mod versions.
     */
    public function handle(): void
    {
        $sptVersionService = new SptVersionService;

        ModVersion::query()
            ->chunk(100, function ($modVersions) use ($sptVersionService): void {
                foreach ($modVersions as $modVersion) {
                    $sptVersionService->resolve($modVersion);
                }
            });
    }
}
