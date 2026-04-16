<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::base')] class extends Component
{
    use ModeratesMod;
    use WithPagination;

    /**
     * The number of results to show on a single page.
     */
    #[Session]
    #[Url]
    public int $perPage = 12;

    /**
     * The options that are available for the per page setting.
     *
     * @var array<int>
     */
    #[Locked]
    public array $perPageOptions = [6, 12, 24, 50];

    /**
     * The timestamp of when the user last viewed this page (before this visit).
     */
    #[Locked]
    public ?string $previousViewedAt = null;

    /**
     * Called when the component is created.
     */
    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $this->previousViewedAt = $user->mods_updated_viewed_at?->toISOString();
    }

    /**
     * Mark the currently visible mods as read by bumping the user's viewed-at timestamp.
     *
     * The component's $previousViewedAt is intentionally left untouched so the list
     * remains visible for this visit — the filter only resets on the next mount.
     */
    public function markAsRead(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $user->update(['mods_updated_viewed_at' => now()]);
        Cache::forget(sprintf('user:%s:nav-updated-mods-count', $user->id));

        Flux::toast(heading: __('Marked as Read'), text: __("These mods won't be shown here on your next visit."), variant: 'success');
    }

    /**
     * Validate that the selected perPage value is an allowed option.
     */
    public function updatedPerPage(int $value): void
    {
        $allowed = collect($this->perPageOptions)->sort()->values();

        if ($allowed->contains($value)) {
            return;
        }

        $this->perPage = $allowed->sortBy(fn (int $item): int => abs($item - $value))->first() ?? 12;
    }

    /**
     * Return data for the view.
     *
     * @return array<string, LengthAwarePaginator<int, Mod>>
     */
    public function with(): array
    {
        $showDisabled = auth()->user()?->isModOrAdmin() ?? false;
        $previousViewedAt = $this->previousViewedAt ? Date::parse($this->previousViewedAt) : null;

        $mods = Mod::query()
            ->select('mods.*')
            ->addSelect(['latest_version_created_at' => function (QueryBuilder $query) use ($showDisabled): void {
                $query->select('mod_versions.created_at')
                    ->from('mod_versions')
                    ->whereColumn('mod_versions.mod_id', 'mods.id')
                    ->whereNotNull('mod_versions.published_at')
                    ->where('mod_versions.disabled', false)
                    ->whereExists(function (QueryBuilder $subQuery) use ($showDisabled): void {
                        $subQuery->select(DB::raw(1))
                            ->from('mod_version_spt_version')
                            ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                            ->whereColumn('mod_version_spt_version.mod_version_id', 'mod_versions.id')
                            ->unless($showDisabled, fn (QueryBuilder $q) => $q->whereNotNull('spt_versions.publish_date')->where('spt_versions.publish_date', '<=', now()));
                    })
                    ->latest('mod_versions.created_at')
                    ->limit(1);
            }])
            ->with(['owner:id,name', 'latestVersion.latestSptVersion'])
            ->unless($showDisabled, fn (Builder $query) => $query->where('mods.disabled', false))
            ->whereExists(function (QueryBuilder $query) use ($showDisabled): void {
                $query->select(DB::raw(1))->from('mod_versions')->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')->whereColumn('mod_versions.mod_id', 'mods.id')->unless($showDisabled, fn (QueryBuilder $query) => $query->where('mod_versions.disabled', false))->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('mod_versions.published_at'))->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('spt_versions.publish_date')->where('spt_versions.publish_date', '<=', now()));
            })
            // Filter to only show mods updated since the user's last visit (if they've visited before)
            ->when(
                $previousViewedAt,
                fn (Builder $query) => $query->whereHas('latestUpdatedVersion', function (Builder $q) use ($previousViewedAt): void {
                    $q->where('created_at', '>', $previousViewedAt);
                }),
            )
            ->orderByDesc('latest_version_created_at')
            ->paginate($this->perPage);

        $this->redirectOutOfBoundsPage($mods);

        return ['mods' => $mods];
    }

    /**
     * Check if the current page is greater than the last page. Redirect if it is.
     *
     * @param  LengthAwarePaginator<int, Mod>  $paginatedMods
     */
    private function redirectOutOfBoundsPage(LengthAwarePaginator $paginatedMods): void
    {
        if ($paginatedMods->currentPage() > $paginatedMods->lastPage()) {
            $this->redirectRoute('mods.recently-updated', ['page' => $paginatedMods->lastPage()]);
        }
    }
};
