<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AddonVersion;
use App\Services\AddonVersionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(60)]
final class ResolveAddonVersionsJob implements ShouldBeUnique, ShouldQueue
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
     * Resolve the mod versions for each of the addon versions.
     */
    public function handle(): void
    {
        $addonVersionService = new AddonVersionService;

        AddonVersion::query()
            ->with('addon')
            ->chunk(100, function (Collection $addonVersions) use ($addonVersionService): void {
                foreach ($addonVersions as $addonVersion) {
                    $addonVersionService->resolve($addonVersion);
                }
            });
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ResolveAddonVersionsJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
