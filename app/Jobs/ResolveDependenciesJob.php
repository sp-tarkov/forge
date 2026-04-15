<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ModVersion;
use App\Services\DependencyVersionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(60)]
#[Backoff([1, 5, 10])]
#[Tries(3)]
final class ResolveDependenciesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Resolve the SPT versions for each of the mod versions.
     */
    public function handle(DependencyVersionService $dependencyVersionService): void
    {
        ModVersion::query()
            ->with('dependencies')
            ->chunk(100, function (Collection $modVersions) use ($dependencyVersionService): void {
                // Eager-load dependent mod versions only for those that have dependencies
                $modVersionsWithDeps = $modVersions->filter(fn (ModVersion $mv): bool => $mv->dependencies->isNotEmpty());
                if ($modVersionsWithDeps->isNotEmpty()) {
                    $modVersionsWithDeps->load(['dependencies.dependentMod.versions']);
                }

                foreach ($modVersions as $modVersion) {
                    $dependencyVersionService->resolve($modVersion);
                }
            });
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ResolveDependenciesJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
