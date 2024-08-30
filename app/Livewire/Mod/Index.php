<?php

namespace App\Livewire\Mod;

use App\Http\Filters\ModFilter;
use App\Models\SptVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    /**
     * The search query value.
     */
    #[Url]
    #[Session]
    public string $query = '';

    /**
     * The sort order value.
     */
    #[Url]
    #[Session]
    public string $order = 'created';

    /**
     * The SPT versions filter value.
     */
    #[Url]
    #[Session]
    public array $sptVersions = [];

    /**
     * The featured filter value.
     */
    #[Url]
    #[Session]
    public string $featured = 'include';

    /**
     * The available SPT versions.
     */
    public Collection $availableSptVersions;

    /**
     * The component mount method, run only once when the component is mounted.
     */
    public function mount(): void
    {
        // TODO: This should ideally be updated to only pull SPT versions that have mods associated with them so that no
        //       empty options are shown in the listing filter.
        $this->availableSptVersions = Cache::remember('availableSptVersions', 60 * 60, function () {
            return SptVersion::select(['id', 'version', 'color_class'])->orderByDesc('version')->get();
        });

        $this->sptVersions = $this->sptVersions ?? $this->getLatestMinorVersions()->pluck('version')->toArray();
    }

    /**
     * Get all hotfix versions of the latest minor SPT version.
     */
    public function getLatestMinorVersions(): Collection
    {
        return $this->availableSptVersions->filter(function (SptVersion $sptVersion) {
            return $sptVersion->isLatestMinor();
        });
    }

    /**
     * The component mount method.
     */
    public function render(): View
    {
        // Fetch the mods using the filters saved to the component properties.
        $filters = [
            'query' => $this->query,
            'featured' => $this->featured,
            'order' => $this->order,
            'sptVersions' => $this->sptVersions,
        ];
        $mods = (new ModFilter($filters))->apply()->paginate(16);

        return view('livewire.mod.index', compact('mods'));
    }

    /**
     * The method to reset the filters.
     */
    public function resetFilters(): void
    {
        $this->query = '';
        $this->sptVersions = $this->getLatestMinorVersions()->pluck('version')->toArray();
        $this->featured = 'include';

        // Clear local storage
        $this->dispatch('clear-filters');
    }

    /**
     * Compute the count of active filters.
     */
    #[Computed]
    public function filterCount(): int
    {
        $count = 0;
        if ($this->query !== '') {
            $count++;
        }
        if ($this->featured !== 'include') {
            $count++;
        }
        $count += count($this->sptVersions);

        return $count;
    }
}
