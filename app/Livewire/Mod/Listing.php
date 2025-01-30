<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Http\Filters\ModFilter;
use App\Models\SptVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
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
     */
    #[Locked]
    public array $perPageOptions = [6, 12, 24, 50];

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
        $this->activeSptVersions ??= Cache::remember('active-spt-versions', 60 * 60, fn (): Collection => SptVersion::getVersionsForLastThreeMinors());

        $this->sptVersions ??= $this->getDefaultSptVersions();
    }

    /**
     * Get the default values for the SPT Versions filter.
     */
    protected function getDefaultSptVersions(): array
    {
        return $this->getLatestMinorVersions()->pluck('version')->toArray();
    }

    /**
     * Get all patch versions of the latest minor SPT version.
     */
    public function getLatestMinorVersions(): Collection
    {
        return $this->activeSptVersions->filter(fn (SptVersion $sptVersion): bool => $sptVersion->isLatestMinor());
    }

    /**
     * The component mount method.
     */
    public function render(): View
    {
        $this->validatePerPage();

        // Fetch the mods using the filters saved to the component properties.
        $filters = [
            'query' => $this->query,
            'featured' => $this->featured,
            'order' => $this->order,
            'sptVersions' => $this->sptVersions,
        ];

        $lengthAwarePaginator = (new ModFilter($filters))->apply()->paginate($this->perPage);

        $this->redirectOutOfBoundsPage($lengthAwarePaginator);

        return view('livewire.mod.listing', ['mods' => $mods]);
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
     */
    private function redirectOutOfBoundsPage(LengthAwarePaginator $lengthAwarePaginator): void
    {
        if ($lengthAwarePaginator->currentPage() > $lengthAwarePaginator->lastPage()) {
            $this->redirectRoute('mods', ['page' => $lengthAwarePaginator->lastPage()]);
        }
    }

    /**
     * The method to reset the filters.
     */
    public function resetFilters(): void
    {
        $this->query = '';
        $this->sptVersions = $this->getDefaultSptVersions();
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
            ++$count;
        }

        if ($this->featured !== 'include') {
            ++$count;
        }

        return $count + count($this->sptVersions);
    }
}
