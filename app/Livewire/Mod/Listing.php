<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Http\Filters\ModFilter;
use App\Models\Mod;
use App\Models\SptVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
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
     * The SPT versions filter value.
     *
     * @var array<int, string>
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
        $this->activeSptVersions ??= Cache::remember('active-spt-versions', 60 * 60, fn (): Collection => SptVersion::getVersionsForLastThreeMinors());
    }

    /**
     * Get or initialize the SPT Versions filter value.
     *
     * @return array<int, string>
     */
    public function getSptVersionsProperty(): array
    {
        if (empty($this->sptVersions)) {
            $this->sptVersions = $this->getLatestMinorVersions()->pluck('version')->toArray();
        }

        return $this->sptVersions;
    }

    /**
     * Get all patch versions of the latest minor SPT version.
     *
     * @return Collection<int, SptVersion>
     */
    public function getLatestMinorVersions(): Collection
    {
        return $this->activeSptVersions->filter(fn (SptVersion $sptVersion): bool => $sptVersion->isLatestMinor());
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
     * Refresh the mod listing.
     */
    #[On('mod-delete')]
    public function refreshListing(): void
    {
        $this->render();
    }

    /**
     * The component mount method.
     */
    public function render(): View
    {
        $this->validatePerPage();

        // Fetch the mods using the filters saved to the component properties.
        $filters = new ModFilter([
            'query' => $this->query,
            'featured' => $this->featured,
            'order' => $this->order,
            'sptVersions' => $this->sptVersions,
        ]);

        $paginatedMods = $filters->apply()->paginate($this->perPage);

        $this->redirectOutOfBoundsPage($paginatedMods);

        return view('livewire.mod.listing', ['mods' => $paginatedMods]);
    }

    /**
     * Validate that the option selected is an option that is available by setting it to the closest available version.
     */
    public function validatePerPage(): void
    {
        $this->perPage = collect($this->perPageOptions)->pipe(function ($data) {
            $closest = null;

            foreach ($data as $item) {
                if ($closest === null || abs($this->perPage - $closest) > abs($item - $this->perPage)) {
                    $closest = $item;
                }
            }

            return $closest;
        });
    }

    /**
     * Check if the current page is greater than the last page. Redirect if it is.
     *
     * @param  LengthAwarePaginator<Mod>  $paginatedMods
     */
    private function redirectOutOfBoundsPage(LengthAwarePaginator $paginatedMods): void
    {
        if ($paginatedMods->currentPage() > $paginatedMods->lastPage()) {
            $this->redirectRoute('mods', ['page' => $paginatedMods->lastPage()]);
        }
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

        return $count + count($this->sptVersions);
    }
}
