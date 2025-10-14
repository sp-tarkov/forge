<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Scopes\PublishedSptVersionScope;
use App\Models\SptVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPinnedModVersionPublishDates implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Find all SPT versions that have a publish_date that has passed (are now published) and have
        // mod versions pinned to them.
        $publishedSptVersions = SptVersion::query()
            ->withoutGlobalScope(PublishedSptVersionScope::class)
            ->whereNotNull('publish_date')
            ->where('publish_date', '<=', Date::now())
            ->whereHas('modVersions', function (mixed $query): void {
                $query->withoutGlobalScopes()
                    ->whereRaw('mod_version_spt_version.pinned_to_spt_publish = true');
            })
            ->with(['modVersions' => function (mixed $query): void {
                $query->withoutGlobalScopes()
                    ->whereRaw('mod_version_spt_version.pinned_to_spt_publish = true');
            }])
            ->get();

        foreach ($publishedSptVersions as $sptVersion) {
            DB::transaction(function () use ($sptVersion): void {
                foreach ($sptVersion->modVersions as $modVersion) {
                    // Get the latest (furthest in future) publish date for all pinned SPT versions
                    $latestPinnedDate = $modVersion->getLatestPinnedSptPublishDate();

                    // If this SPT version's publish date was the controlling one (latest), or if there are no more
                    // unpublished pinned versions, set the mod version's publish date
                    if (is_null($latestPinnedDate) || $latestPinnedDate <= Date::now()) {
                        // All pinned SPT versions are now published
                        // Set the mod version's published_at to now if it wasn't already set
                        if (is_null($modVersion->published_at)) {
                            $modVersion->published_at = Date::now();
                            $modVersion->save();

                            Log::info('Automatically published mod version', [
                                'mod_version_id' => $modVersion->id,
                                'mod_name' => $modVersion->mod->name,
                                'version' => $modVersion->version,
                                'triggered_by_spt' => $sptVersion->version,
                            ]);
                        }
                    }

                    // Clear the pinning for this SPT version since it's now published
                    $modVersion->sptVersions()->withoutGlobalScopes()->updateExistingPivot($sptVersion->id, [
                        'pinned_to_spt_publish' => false,
                    ]);

                    Log::info('Cleared pinning for published SPT version', [
                        'mod_version_id' => $modVersion->id,
                        'spt_version' => $sptVersion->version,
                    ]);
                }
            });
        }
    }
}
