<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AddonVersion;
use App\Services\AddonVersionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResolveAddonVersionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
