<?php

namespace App\Jobs;

use App\Models\ModVersion;
use App\Services\DependencyVersionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResolveDependenciesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Resolve the SPT versions for each of the mod versions.
     */
    public function handle(): void
    {
        $dependencyVersionService = new DependencyVersionService;

        foreach (ModVersion::all() as $modVersion) {
            $dependencyVersionService->resolve($modVersion);
        }
    }
}
