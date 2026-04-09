<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SptVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SptVersionModCountsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Recalculate the mod counts for each SPT version.
     */
    public function handle(): void
    {
        SptVersion::query()
            ->get()
            ->each(function (SptVersion $sptVersion): void {
                $sptVersion->updateModCount();
            });
    }
}
