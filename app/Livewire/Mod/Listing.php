<?php

namespace App\Livewire\Mod;

use App\Http\Filters\ModFilter;
use App\Models\SptVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Listing extends Component
{
    use WithPagination;

    /**
     * The search query value.
     */
    #[Session]
    #[Url]
    public string $query = '';

    /**
     * The sort order value.
     */
    #[Session]
    #[Url]
    public string $order = 'created';

    #[Url]
    public int $resultsPerPage = 12;

    /**
     * The SPT versions filter value.
     */
    #[Session]
    #[Url]
    public array $sptVersions = [];

    /**
     * The featured filter value.
     */
    #[Session]
    #[Url]
    public string $featured = 'include';

    /**
     * The available SPT versions.
     *
     * @var Collection<int, SptVersion>
     */
    public Collection $activeSptVersions;

    /**
     * The component mount method, run only once when the component is mounted.
     */
    public function mount(): void
    {
        $this->activeSptVersions = $this->activeSptVersions ?? Cache::remember('active-spt-versions', 60 * 60, function () {
            return SptVersion::getVersionsForLastThreeMinors();
        });

        $this->sptVersions = $this->sptVersions ?? $this->getLatestMinorVersions()->pluck('version')->toArray();
    }

    /**
     * Get all patch versions of the latest minor SPT version.
     */
    public function getLatestMinorVersions(): Collection
    {
        return $this->activeSptVersions->filter(function (SptVersion $sptVersion) {
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
        $mods = (new ModFilter($filters))->apply()->paginate($this->resultsPerPage);

        $this->redirectOutOfBoundsPage($mods);

        return view('livewire.mod.listing', compact('mods'));
    }

    /**
     * Check if the current page is greater than the last page. Redirect if it is.
     */
    private function redirectOutOfBoundsPage(LengthAwarePaginator $mods): void
    {
        if ($mods->currentPage() > $mods->lastPage()) {
            $this->redirectRoute('mods', ['page' => $mods->lastPage()]);
        }
    }

    /**
     * The method to reset the filters.
     */
    public function resetFilters(): void
    {
        $this->query = '';
        $this->sptVersions = $this->getLatestMinorVersions()->pluck('version')->toArray();
        $this->featured = 'include';
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
