<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(120)]
#[Backoff([1, 5, 10])]
#[Tries(3)]
final class TombstoneModInListsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $modId) {}

    /**
     * Mark the mod and all of its addons as tombstones on every non-Favourites list that currently references them.
     *
     * Favourites are intentionally untouched - users keep full control of their personal Favourites list regardless
     * of the author's opt-out.
     */
    public function handle(): void
    {
        $mod = Mod::query()->find($this->modId);
        if (! $mod instanceof Mod) {
            return;
        }

        // Author may have reversed the opt-out between observer dispatch and job run; bail rather than tombstone.
        if (! $mod->lists_disabled) {
            return;
        }

        $addonNames = Addon::query()
            ->where('mod_id', $mod->id)
            ->pluck('name', 'id');

        DB::transaction(function () use ($mod, $addonNames): void {
            $now = now();

            // Tombstone the mod itself wherever it appears on a non-Favourites list.
            ModListItem::query()
                ->active()
                ->where('listable_type', Mod::class)
                ->where('listable_id', $mod->id)
                ->whereHas('modList', $this->nonFavouriteListConstraint())
                ->update([
                    'tombstoned_at' => $now,
                    'tombstoned_name' => $mod->name,
                    'updated_at' => $now,
                ]);

            // Tombstone each addon under this mod individually so each list entry captures the original addon name.
            foreach ($addonNames as $addonId => $addonName) {
                ModListItem::query()
                    ->active()
                    ->where('listable_type', Addon::class)
                    ->where('listable_id', $addonId)
                    ->whereHas('modList', $this->nonFavouriteListConstraint())
                    ->update([
                        'tombstoned_at' => $now,
                        'tombstoned_name' => $addonName,
                        'updated_at' => $now,
                    ]);
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('TombstoneModInListsJob failed', [
            'mod_id' => $this->modId,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * @return Closure(Builder<ModList>): Builder<ModList>
     */
    private function nonFavouriteListConstraint(): Closure
    {
        return fn (Builder $query): Builder => $query->where('is_default', false);
    }
}
