<?php

declare(strict_types=1);

use App\Http\Filters\ModFilter;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Support\DataTransferObjects\ActiveFilterChip;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
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
     * The maximum number of individual version chips shown before collapsing into a single summary chip.
     */
    private const int VERSION_CHIP_LIMIT = 5;

    /**
     * The search query value.
     */
    #[Url]
    public mixed $query = '';

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
    #[Url]
    public mixed $featured = 'include';

    /**
     * The AI generated content filter value.
     */
    #[Url(as: 'ai')]
    public mixed $aiContent = 'include';

    /**
     * The category filter value.
     */
    #[Url(except: '')]
    public mixed $category = '';

    /**
     * The Fika Compatibility filter value.
     */
    #[Url]
    public mixed $fikaCompatibility = false;

    /**
     * Called when a component is created.
     */
    public function mount(): void
    {
        // Normalize URL parameters to ensure they're the correct type (handles malformed URLs)
        if (! is_string($this->query)) {
            $this->query = '';
        }

        if (! is_string($this->featured)) {
            $this->featured = 'include';
        }

        if (! is_string($this->aiContent)) {
            $this->aiContent = 'include';
        }

        if (! is_string($this->category)) {
            $this->category = '';
        }

        if (! is_bool($this->fikaCompatibility)) {
            $this->fikaCompatibility = false;
        }

        // Set default versions if none provided via URL
        if (in_array($this->sptVersions, ['', '0', []], true)) {
            $this->sptVersions = $this->defaultSptVersions();
        }
    }

    /**
     * The method to reset the filters.
     */
    public function resetFilters(): void
    {
        unset($this->availableSptVersions, $this->splitSptVersions);

        $this->query = '';
        $this->sptVersions = $this->defaultSptVersions();
        $this->featured = 'include';
        $this->aiContent = 'include';
        $this->category = '';
        $this->fikaCompatibility = false;

        unset($this->splitSptVersions); // Clear computed property cache
    }

    /**
     * Clear a single filter back to its default value.
     */
    public function clearFilter(string $filter): void
    {
        match ($filter) {
            'query' => $this->query = '',
            'featured' => $this->featured = 'include',
            'ai' => $this->aiContent = 'include',
            'category' => $this->category = '',
            'fika' => $this->fikaCompatibility = false,
            'versions' => $this->sptVersions = $this->defaultSptVersions(),
            default => null,
        };

        $this->resetPage();
        unset($this->splitSptVersions); // Clear cached computed property
    }

    /**
     * Fetch the default value for the sptVersions property.
     *
     * @return array<int, string>
     */
    public function defaultSptVersions(): array
    {
        /** @var array<int, string> */
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
     * Validate that the selected perPage value is an allowed option, resetting to the closest valid option.
     */
    public function updatedPerPage(int $value): void
    {
        $allowed = collect($this->perPageOptions)->sort()->values();

        if ($allowed->contains($value)) {
            return; // The value is allowed.
        }

        // Find the closest allowed value.
        $this->perPage = $allowed->sortBy(fn (int $item): int => abs($item - $value))->first() ?? 12;
    }

    /**
     * Validate the order value.
     */
    public function updatedOrder(string $value): void
    {
        if (! in_array($value, ['created', 'updated', 'downloaded'], true)) {
            $this->order = 'created';
        }
    }

    /**
     * Get the available SPT versions.
     *
     * @return Collection<int, SptVersion>
     */
    #[Computed]
    public function availableSptVersions(): Collection
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
            ->whereIn('id', $ids)
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->get();
    }

    /**
     * Get the available mod categories.
     *
     * @return Collection<int, ModCategory>
     */
    #[Computed]
    public function availableCategories(): Collection
    {
        return ModCategory::cachedOrdered();
    }

    /**
     * Compute the split of the active SPT versions.
     *
     * @return array<int, Collection<int, SptVersion>>
     */
    #[Computed]
    public function splitSptVersions(): array
    {
        $versions = $this->availableSptVersions;
        $half = (int) ceil($versions->count() / 2);

        return [$versions->take($half), $versions->slice($half)];
    }

    /**
     * Compute the dismissible chips for every active, non-default filter.
     *
     * @return array<int, ActiveFilterChip>
     */
    #[Computed]
    public function activeFilterChips(): array
    {
        $chips = [];

        if (is_string($this->query) && $this->query !== '') {
            $chips[] = new ActiveFilterChip('query', __('Search: ":query"', ['query' => $this->query]), "clearFilter('query')");
        }

        $chips = [...$chips, ...$this->versionFilterChips()];

        if ($this->featured === 'exclude') {
            $chips[] = new ActiveFilterChip('featured', __('Featured: excluded'), "clearFilter('featured')");
        } elseif ($this->featured === 'only') {
            $chips[] = new ActiveFilterChip('featured', __('Featured only'), "clearFilter('featured')");
        }

        if ($this->aiContent === 'exclude') {
            $chips[] = new ActiveFilterChip('ai', __('AI generation: excluded'), "clearFilter('ai')");
        } elseif ($this->aiContent === 'only') {
            $chips[] = new ActiveFilterChip('ai', __('AI generation only'), "clearFilter('ai')");
        }

        if ($this->fikaCompatibility === true) {
            $chips[] = new ActiveFilterChip('fika', __('Fika compatible'), "clearFilter('fika')");
        }

        if (is_string($this->category) && $this->category !== '') {
            $categoryTitle = $this->availableCategories->firstWhere('slug', $this->category)->title ?? $this->category;
            $chips[] = new ActiveFilterChip('category', $categoryTitle, "clearFilter('category')");
        }

        return $chips;
    }

    /**
     * Compute the count of active filters, matching the number of visible filter chips.
     */
    #[Computed]
    public function filterCount(): int
    {
        return count($this->activeFilterChips);
    }

    /**
     * Compute the display label for the current sort order, falling back to the default sort for unknown values.
     */
    #[Computed]
    public function orderLabel(): string
    {
        return match ($this->order) {
            'updated' => __('Recently Updated'),
            'downloaded' => __('Most Downloaded'),
            default => __('Newest'),
        };
    }

    /**
     * Get the display version for a mod, preferring modern versions with a legacy fallback.
     */
    public function getDisplayVersion(Mod $mod, bool $includeLegacy): ?ModVersion
    {
        $version = $mod->latestVersion;

        if ($includeLegacy && ! $version && $mod->latestLegacyVersion) {
            return $mod->latestLegacyVersion;
        }

        return $version;
    }

    /**
     * Return data for the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        // Fetch the mods using the filters saved to the component properties.
        $filters = new ModFilter([
            'query' => $this->query,
            'featured' => $this->featured,
            'aiContent' => $this->aiContent,
            'order' => $this->order,
            'sptVersions' => $this->sptVersions,
            'category' => $this->category,
            'fikaCompatibility' => $this->fikaCompatibility,
        ]);

        $paginatedMods = $filters->apply()->paginate($this->perPage);

        // Determine if we should load legacy versions
        $includeLegacy = $this->sptVersions === 'all' || (is_array($this->sptVersions) && in_array('legacy', $this->sptVersions)) || $this->sptVersions === 'legacy';

        // Eager load appropriate version relationship
        /** @var Collection<int, Mod> $modCollection */
        $modCollection = $paginatedMods->getCollection();
        if ($includeLegacy) {
            $modCollection->loadMissing(['latestVersion', 'latestLegacyVersion']);
        } else {
            $modCollection->loadMissing('latestVersion');
        }

        $this->redirectOutOfBoundsPage($paginatedMods);

        return ['mods' => $paginatedMods, 'includeLegacy' => $includeLegacy];
    }

    /**
     * Build the version chips: the "all" state, a per-version list, or a summary chip when many are selected.
     *
     * @return array<int, ActiveFilterChip>
     */
    private function versionFilterChips(): array
    {
        if ($this->sptVersions === 'all') {
            return [new ActiveFilterChip('versions-all', __('All SPT versions'), "toggleVersionFilter('all')")];
        }

        if ($this->isDefaultVersionSelection()) {
            return [];
        }

        $versions = $this->ensureVersionsArray();

        if (count($versions) > self::VERSION_CHIP_LIMIT) {
            return [new ActiveFilterChip('versions-summary', __(':count SPT versions', ['count' => count($versions)]), "clearFilter('versions')")];
        }

        return array_map(
            fn (string $version): ActiveFilterChip => new ActiveFilterChip(
                'version-'.$version,
                $version === 'legacy' ? __('Legacy versions') : __('SPT :version', ['version' => $version]),
                sprintf("toggleVersionFilter('%s')", $version),
            ),
            $versions,
        );
    }

    /**
     * Determine whether the current version selection matches the default set, ignoring order.
     */
    private function isDefaultVersionSelection(): bool
    {
        if (! is_array($this->sptVersions)) {
            return false;
        }

        $current = $this->sptVersions;
        $default = $this->defaultSptVersions();
        sort($current);
        sort($default);

        return $current === $default;
    }

    /**
     * Toggle "All Versions" on or off.
     */
    private function toggleAllVersions(): void
    {
        $this->sptVersions = $this->sptVersions === 'all' ? $this->defaultSptVersions() : 'all';
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
        if ($this->sptVersions === []) {
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
        $key = array_search($version, $versions, true);

        if ($key !== false) {
            unset($versions[$key]);

            return array_values($versions);
        }

        return [...$versions, $version];
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
};
