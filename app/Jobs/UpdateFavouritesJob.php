<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Mod;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(60)]
#[Backoff([1, 5, 10])]
#[Tries(3)]
final class UpdateFavouritesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Recalculate the denormalized favourite counts for each mod: aggregates active list items on non-disabled
     * Favourites lists in a single grouped query, then updates only the mods whose stored count has drifted.
     */
    public function handle(): void
    {
        /** @var array<int, int> $favouriteCounts */
        $favouriteCounts = DB::table('mod_list_items')
            ->join('mod_lists', 'mod_lists.id', '=', 'mod_list_items.mod_list_id')
            ->where('mod_list_items.listable_type', Mod::class)
            ->whereNull('mod_list_items.tombstoned_at')
            ->where('mod_lists.is_default', true)
            ->where('mod_lists.disabled', false)
            ->groupBy('mod_list_items.listable_id')
            ->selectRaw('mod_list_items.listable_id AS mod_id, COUNT(*) AS favourites')
            ->pluck('favourites', 'mod_id')
            ->all();

        Mod::query()
            ->select(['id', 'favourites_count'])
            ->chunkById(1000, function (Collection $mods) use ($favouriteCounts): void {
                $modIdsByCount = [];

                /** @var Mod $mod */
                foreach ($mods as $mod) {
                    $count = $favouriteCounts[$mod->id] ?? 0;
                    if ($count !== $mod->favourites_count) {
                        $modIdsByCount[$count][] = $mod->id;
                    }
                }

                foreach ($modIdsByCount as $count => $modIds) {
                    DB::table('mods')
                        ->whereIn('id', $modIds)
                        ->update(['favourites_count' => $count]);
                }
            });
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('UpdateFavouritesJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
