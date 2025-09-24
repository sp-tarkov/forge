<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Http\Filters\ModFilter;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\SptVersion;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ModeratesMod;
    use WithPagination;

    /**
     * The search query value.
     */
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
     * Can be "all" for all versions, "legacy" for legacy versions, or an array of version strings.
     *
     * @var string|array<int, string>
     */
    #[Url(as: 'versions')]
    public string|array $sptVersions = [];

    /**
     * The featured filter value.
     */
    #[Session]
    #[Url]
    public string $featured = 'include';

    /**
     * The category filter value.
     */
    #[Url(except: '')]
    public string $category = '';

    /**
     * The available SPT versions.
     *
     * @var Collection<int, SptVersion>
     */
    public Collection $availableSptVersions;

    /**
     * The available mod categories.
     *
     * @var Collection<int, ModCategory>
     */
    public Collection $availableCategories;

    /**
     * Called when a component is created.
     */
    public function mount(): void
    {
        // Fetch all versions in the last three minor versions
        $this->availableSptVersions ??= Cache::remember(
            'active-spt-versions',
            600,
            fn (): Collection => SptVersion::getVersionsForLastThreeMinors()
        );

        // Fetch all mod categories
        $this->availableCategories = Cache::remember(
            'mod-categories',
            600,
            fn (): Collection => ModCategory::query()->orderBy('title')->get()
        );

        // Set default versions if none provided via URL
        if (empty($this->sptVersions)) {
            $this->sptVersions = $this->defaultSptVersions();
        }
    }

    /**
     * The method to reset the filters.
     */
    public function resetFilters(): void
    {
        $this->query = '';
        $this->sptVersions = $this->defaultSptVersions();
        $this->featured = 'include';
        $this->category = '';

        unset($this->splitSptVersions); // Clear computed property cache
    }

    /**
     * Fetch the default value for the sptVersions property.
     *
     * @return array<int, string>
     */
    public function defaultSptVersions(): array
    {
        return SptVersion::getLatestMinorVersions()->pluck('version')->toArray();
    }

    /**
     * Toggle a version filter on or off.
     */
    public function toggleVersionFilter(string $version): void
    {
        if ($version === 'all') {
            $this->toggleAllVersions();
        } else {
            $this->toggleIndividualVersion($version);
        }

        $this->resetPage();
        unset($this->splitSptVersions); // Clear cached computed property
    }

    /**
     * Toggle "All Versions" on or off.
     */
    private function toggleAllVersions(): void
    {
        $this->sptVersions = ($this->sptVersions === 'all')
            ? $this->defaultSptVersions()
            : 'all';
    }

    /**
     * Toggle an individual version on or off.
     */
    private function toggleIndividualVersion(string $version): void
    {
        if ($this->sptVersions === 'all') {
            $this->sptVersions = [$version];

            return;
        }

        $currentVersions = $this->ensureVersionsArray();
        $this->sptVersions = $this->toggleVersionInArray($version, $currentVersions);

        // If no versions are selected after toggling, switch to "all"
        if (empty($this->sptVersions)) {
            $this->sptVersions = 'all';
        }
    }

    /**
     * Ensure sptVersions is treated as an array.
     *
     * @return array<int, string>
     */
    private function ensureVersionsArray(): array
    {
        return is_array($this->sptVersions) ? $this->sptVersions : [$this->sptVersions];
    }

    /**
     * Toggle a version in the given array and return the result.
     *
     * @param  array<int, string>  $versions
     * @return array<int, string>
     */
    private function toggleVersionInArray(string $version, array $versions): array
    {
        $key = array_search($version, $versions);

        if ($key !== false) {
            unset($versions[$key]);

            return array_values($versions);
        }

        return [...$versions, $version];
    }

    /**
     * Validate that the selected perPage value is an allowed option, resetting to the closest valid option.
     */
    public function updatedPerPage(int $value): void
    {
        $allowed = collect($this->perPageOptions)->sort()->values();

        if ($allowed->contains($value)) {
            return; // The value is allowed.
        }

        // Find the closest allowed value.
        $this->perPage = $allowed->sortBy(fn (int $item): int => abs($item - $value))->first();
    }

    /**
     * Validate the order value.
     */
    public function updatedOrder(string $value): void
    {
        if (! in_array($value, ['created', 'updated', 'downloaded'])) {
            $this->order = 'created';
        }
    }

    /**
     * Check if the current page is greater than the last page. Redirect if it is.
     *
     * @param  LengthAwarePaginator<int, Mod>  $paginatedMods
     */
    private function redirectOutOfBoundsPage(LengthAwarePaginator $paginatedMods): void
    {
        if ($paginatedMods->currentPage() > $paginatedMods->lastPage()) {
            $this->redirectRoute('mods', ['page' => $paginatedMods->lastPage()]);
        }
    }

    /**
     * Compute the split of the active SPT versions.
     *
     * @return array<int, Collection<int, SptVersion>>
     */
    #[Computed(cache: true)]
    public function splitSptVersions(): array
    {
        $versions = $this->availableSptVersions;
        $half = (int) ceil($versions->count() / 2);

        return [
            $versions->take($half),
            $versions->slice($half),
        ];
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

        if ($this->category !== '') {
            $count++;
        }

        // Count sptVersions filter if it's not 'all' and not empty
        if (is_array($this->sptVersions)) {
            $count += count($this->sptVersions);
        } elseif ($this->sptVersions !== 'all' && ! empty($this->sptVersions)) {
            $count++;
        }

        return $count;
    }

    /**
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        // Fetch the mods using the filters saved to the component properties.
        $filters = new ModFilter([
            'query' => $this->query,
            'featured' => $this->featured,
            'order' => $this->order,
            'sptVersions' => $this->sptVersions,
            'category' => $this->category,
        ]);

        $paginatedMods = $filters->apply()->paginate($this->perPage);

        $this->redirectOutOfBoundsPage($paginatedMods);

        return view('livewire.page.mod.index', ['mods' => $paginatedMods]);
    }
}
