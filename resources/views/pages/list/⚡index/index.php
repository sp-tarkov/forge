<?php

declare(strict_types=1);

use App\Models\ModList;
use App\Models\SptVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::base')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'spt', except: null)]
    public ?int $sptVersionId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSptVersionId(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->sptVersionId = null;
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, ModList>
     */
    #[Computed]
    public function lists(): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, ModList> $paginator */
        $paginator = ModList::query()
            ->discoverable()
            ->with(['owner:id,name', 'sptVersion'])
            ->withCount('items')
            ->when($this->search !== '', fn (Builder $q): Builder => $q->whereLike('title', '%'.$this->search.'%'))
            ->when($this->sptVersionId !== null, fn (Builder $q): Builder => $q->where('spt_version_id', $this->sptVersionId))
            ->latest('updated_at')
            ->paginate(12);

        return $paginator;
    }

    /**
     * @return Collection<int, SptVersion>
     */
    #[Computed]
    public function sptVersionOptions(): Collection
    {
        $isAdmin = auth()->user()?->isModOrAdmin() ?? false;
        $cacheKey = $isAdmin ? 'spt-versions:filter-ids:admin' : 'spt-versions:filter-ids:user';

        /** @var array<int, int> $ids */
        $ids = Cache::flexible(
            $cacheKey,
            [5 * 60, 10 * 60],
            fn (): array => SptVersion::getVersionsForLastThreeMinors($isAdmin)->pluck('id')->all(),
        );

        return SptVersion::query()
            ->select(['id', 'version', 'version_major', 'version_minor', 'version_patch', 'version_labels', 'color_class'])
            ->whereIn('id', $ids)
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->get();
    }
};
