<?php

declare(strict_types=1);

use App\Models\Mod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    /**
     * The count of mods created since the user's last view.
     */
    public int $createdCount = 0;

    /**
     * Initialize the component.
     */
    public function mount(): void
    {
        $this->loadCreatedCount();
    }

    /**
     * Refresh the count when navigation occurs.
     */
    #[On('navigate')]
    public function refreshOnNavigate(): void
    {
        // Refresh the user model to get the updated timestamp
        $this->loadCreatedCount();
    }

    /**
     * Load the count of mods created since the user's last view.
     */
    private function loadCreatedCount(): void
    {
        $user = Auth::user();

        // If the user hasn't viewed the page yet, show no badge (first visit = no badge)
        if ($user === null) {
            $this->createdCount = 0;

            return;
        }

        // Refresh to get the latest timestamp (in case it was just updated)
        $user->refresh();

        if ($user->mods_created_viewed_at === null) {
            $this->createdCount = 0;

            return;
        }

        $lastViewed = $user->mods_created_viewed_at;
        $showDisabled = $user->isModOrAdmin();

        $this->createdCount = Mod::query()
            ->whereExists(function (QueryBuilder $query) use ($showDisabled): void {
                $query->select(DB::raw(1))->from('mod_versions')->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')->whereColumn('mod_versions.mod_id', 'mods.id')->unless($showDisabled, fn(QueryBuilder $query) => $query->where('mod_versions.disabled', false))->unless($showDisabled, fn(QueryBuilder $query) => $query->whereNotNull('mod_versions.published_at'))->unless($showDisabled, fn(QueryBuilder $query) => $query->whereNotNull('spt_versions.publish_date')->where('spt_versions.publish_date', '<=', now()));
            })
            ->where('mods.created_at', '>', $lastViewed)
            ->unless($showDisabled, fn(Builder $query) => $query->where('disabled', false))
            ->count();
    }
}; ?>

<div>
    @if ($createdCount > 0)
        <span
            class="flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold text-white"
        >
            {{ $createdCount > 99 ? '99+' : $createdCount }}
        </span>
    @endif
</div>
