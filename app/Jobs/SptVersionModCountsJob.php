<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SptVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SptVersionModCountsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
