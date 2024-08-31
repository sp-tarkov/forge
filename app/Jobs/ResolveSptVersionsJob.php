<?php

namespace App\Jobs;

use App\Models\ModVersion;
use App\Services\SptVersionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResolveSptVersionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Resolve the SPT versions for each of the mod versions.
     */
    public function handle(): void
    {
        $sptVersionService = new SptVersionService;

        foreach (ModVersion::all() as $modVersion) {
            $sptVersionService->resolve($modVersion);
        }
    }
}
