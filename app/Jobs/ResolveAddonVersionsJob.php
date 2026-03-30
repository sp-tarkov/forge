<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Foundation\Queue\Queueable;
use App\Models\AddonVersion;
use App\Services\AddonVersionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;

class ResolveAddonVersionsJob implements ShouldQueue
{
    use Queueable;

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
}
