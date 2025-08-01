<?php

declare(strict_types=1);

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
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Resolve the SPT versions for each of the mod versions.
     */
    public function handle(): void
    {
        $dependencyVersionService = new DependencyVersionService;

        ModVersion::query()
            ->with('dependencies')
            ->chunk(100, function ($modVersions) use ($dependencyVersionService): void {
                // Eager-load dependent mod versions only for those that have dependencies
                $modVersionsWithDeps = $modVersions->filter(fn ($mv) => $mv->dependencies->isNotEmpty());
                if ($modVersionsWithDeps->isNotEmpty()) {
                    $modVersionsWithDeps->load(['dependencies.dependentMod.versions']);
                }

                foreach ($modVersions as $modVersion) {
                    $dependencyVersionService->resolve($modVersion);
                }
            });
    }
}
