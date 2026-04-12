<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ModVersion;
use App\Services\DependencyVersionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(60)]
final class ResolveDependenciesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [1, 5, 10];

    /**
     * Resolve the SPT versions for each of the mod versions.
     */
    public function handle(): void
    {
        $dependencyVersionService = new DependencyVersionService;

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
