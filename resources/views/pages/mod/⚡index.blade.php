<?php

declare(strict_types=1);

use App\Http\Filters\ModFilter;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\SptVersion;
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

new #[Layout('layouts::base')] class extends Component {
    use ModeratesMod;
    use WithPagination;

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
    #[Session]
    #[Url]
    public mixed $featured = 'include';

    /**
     * The category filter value.
     */
    #[Url(except: '')]
    public mixed $category = '';

    /**
     * The Fika Compatibility filter value.
     */
    #[Session]
    #[Url]
    public mixed $fikaCompatibility = false;

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
        $this->loadAvailableSptVersions();

        // Fetch all mod categories
        $this->availableCategories = Cache::flexible(
            'mod-categories',
            [5 * 60, 10 * 60], // 5 minutes stale, 10 minutes expire
            fn(): Collection => ModCategory::query()->orderBy('title')->get(),
        );

        // Normalize URL parameters to ensure they're the correct type (handles malformed URLs)
        if (!is_string($this->query)) {
            $this->query = '';
        }

        if (!is_string($this->featured)) {
            $this->featured = 'include';
        }

        if (!is_string($this->category)) {
            $this->category = '';
        }

        if (!is_bool($this->fikaCompatibility)) {
            $this->fikaCompatibility = false;
        }

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
        $this->loadAvailableSptVersions();

        $this->query = '';
        $this->sptVersions = $this->defaultSptVersions();
        $this->featured = 'include';
        $this->category = '';
        $this->fikaCompatibility = false;

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
     * Validate that the selected perPage value is an allowed option, resetting to the closest valid option.
     */
    public function updatedPerPage(int $value): void
    {
        $allowed = collect($this->perPageOptions)->sort()->values();

        if ($allowed->contains($value)) {
            return; // The value is allowed.
        }

        // Find the closest allowed value.
        $this->perPage = $allowed->sortBy(fn(int $item): int => abs($item - $value))->first();
    }

    /**
     * Validate the order value.
     */
    public function updatedOrder(string $value): void
    {
        if (!in_array($value, ['created', 'updated', 'downloaded'])) {
            $this->order = 'created';
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

        return [$versions->take($half), $versions->slice($half)];
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

        if ($this->fikaCompatibility === true) {
            $count++;
        }

        // Count sptVersions filter if it's not 'all' and not empty
        if (is_array($this->sptVersions)) {
            $count += count($this->sptVersions);
        } elseif ($this->sptVersions !== 'all' && !empty($this->sptVersions)) {
            $count++;
        }

        return $count;
    }

    /**
     * Return data for the view.
     *
     * @return array<string, LengthAwarePaginator<int, Mod>>
     */
    public function with(): array
    {
        // Ensure SPT versions are up to date for current auth state
        $this->loadAvailableSptVersions();

        // Fetch the mods using the filters saved to the component properties.
        $filters = new ModFilter([
            'query' => $this->query,
            'featured' => $this->featured,
            'order' => $this->order,
            'sptVersions' => $this->sptVersions,
            'category' => $this->category,
            'fikaCompatibility' => $this->fikaCompatibility,
        ]);

        $paginatedMods = $filters->apply()->paginate($this->perPage);

        $this->redirectOutOfBoundsPage($paginatedMods);

        return ['mods' => $paginatedMods];
    }

    /**
     * Load available SPT versions based on current user role.
     */
    private function loadAvailableSptVersions(): void
    {
        // Fetch all versions in the last three minor versions
        $isAdmin = auth()->user()?->isModOrAdmin() ?? false;
        $cacheKey = $isAdmin ? 'spt-versions:filter-list:admin' : 'spt-versions:filter-list:user';

        $this->availableSptVersions = Cache::flexible(
            $cacheKey,
            [5 * 60, 10 * 60], // 5 minutes stale, 10 minutes expire
            fn(): Collection => SptVersion::getVersionsForLastThreeMinors($isAdmin),
        );

        // Clear the computed property cache
        unset($this->splitSptVersions);
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
}; ?>

<x-slot:title>
    {!! __('Mods - Find the best SPT Mods - The Forge') !!}
</x-slot>

<x-slot:description>
    {!! __(
        'Explore an enhanced Single Player Tarkov experience with the mods available below. Not sure where to start? Check out the featured mods; they are hand-picked by our team and a solid choice to get you started.',
    ) !!}
</x-slot>

<x-slot:rssFeeds>
    <link
        rel="alternate"
        type="application/rss+xml"
        title="The Forge - SPT Mods RSS Feed"
        href="{{ route('mods.rss') }}"
    />
</x-slot>

<x-slot:header>
    <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
            <flux:icon.cube-transparent class="w-5 h-5" />
            {{ __('Mod Listings') }}
        </h2>
        @auth
            @can('create', [App\Models\Mod::class])
                <flux:button
                    href="{{ route('mod.guidelines') }}"
                    size="sm"
                >{{ __('Create New Mod') }}</flux:button>
            @else
                <flux:tooltip content="Must enable MFA to create mods.">
                    <div>
                        <flux:button
                            disabled="true"
                            size="sm"
                        >{{ __('Create New Mod') }}</flux:button>
                    </div>
                </flux:tooltip>
            @endcan
        @endauth
    </div>
</x-slot>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div
        class="px-4 py-8 sm:px-6 lg:px-8 bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 rounded-none sm:rounded-lg">
        <h1 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-200">{{ __('Mods') }}</h1>
        <p class="mt-4 text-base text-gray-800 dark:text-gray-300">{!! __(
            'Explore an enhanced <abbr title="Single Player Tarkov">SPT</abbr> experience with the mods available below. Not sure where to start? Check out the featured mods; they are hand-picked by our team and a solid choice to get you started.',
        ) !!}</p>
        <search class="lg:hidden relative group mt-6">
            <div class="pointer-events-none absolute inset-y-0 left-2 flex items-center">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 16 16"
                    fill="currentColor"
                    class="h-5 w-5 text-gray-500"
                >
                    <path
                        fill-rule="evenodd"
                        d="M9.965 11.026a5 5 0 1 1 1.06-1.06l2.755 2.754a.75.75 0 1 1-1.06 1.06l-2.755-2.754ZM10.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z"
                        clip-rule="evenodd"
                    />
                </svg>
            </div>
            <input
                wire:model.live.debounce.300ms="query"
                class="w-full rounded-md border-0 bg-white dark:bg-gray-700 py-1.5 pl-10 pr-3 text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-400 dark:ring-gray-700 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-gray-700 dark:focus:bg-gray-200 dark:focus:text-black dark:focus:ring-0 sm:text-sm sm:leading-6"
                placeholder="{{ __('Search Mods') }}"
            />
        </search>

        <section
            x-data="{ isFilterOpen: false }"
            x-on:click.away="isFilterOpen = false"
            aria-labelledby="filter-heading"
            class="my-8 grid items-center border-t border-gray-400 dark:border-gray-700"
        >
            <h2
                id="filter-heading"
                class="sr-only"
            >{{ __('Filters') }}</h2>
            <div class="relative col-start-1 row-start-1 py-4 border-b border-gray-400 dark:border-gray-700">
                <div
                    class="mx-auto flex flex-wrap items-center justify-center sm:justify-start gap-2 lg:gap-0 lg:flex-nowrap max-w-7xl text-sm">
                    {{-- Filters button --}}
                    <div
                        class="flex items-center border-r border-gray-400 dark:border-gray-700 flex-shrink-0 order-1 self-stretch">
                        <button
                            type="button"
                            x-on:click="isFilterOpen = !isFilterOpen"
                            class="group flex items-center font-medium text-gray-800 dark:text-gray-300 pr-3 sm:pr-4 lg:pr-4 xl:pr-6 whitespace-nowrap"
                            aria-controls="disclosure-1"
                            aria-expanded="false"
                        >
                            <svg
                                class="mr-1.5 sm:mr-2 h-4 w-4 sm:h-5 sm:w-5 flex-none text-gray-500 group-hover:text-gray-600 dark:text-gray-600"
                                aria-hidden="true"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <span class="hidden min-[400px]:inline">{{ $this->filterCount }}</span>
                            <span class="ml-1">{{ __('Filters') }}</span>
                        </button>
                    </div>

                    {{-- Search bar (only on large screens and up) --}}
                    <div
                        class="hidden lg:flex items-center border-r border-gray-400 dark:border-gray-700 flex-1 min-w-[280px] max-w-md order-2 self-stretch">
                        <search class="flex relative group px-3 sm:px-4 lg:px-4 xl:px-6 w-full">
                            <div
                                class="pointer-events-none absolute inset-y-0 left-5 sm:left-6 lg:left-8 flex items-center">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 16 16"
                                    fill="currentColor"
                                    class="h-4 w-4 sm:h-5 sm:w-5 text-gray-500"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M9.965 11.026a5 5 0 1 1 1.06-1.06l2.755 2.754a.75.75 0 1 1-1.06 1.06l-2.755-2.754ZM10.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </div>
                            <input
                                wire:model.live.debounce.300ms="query"
                                class="w-full rounded-md border-0 bg-white dark:bg-gray-700 py-1.5 pl-8 sm:pl-10 pr-3 text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-400 dark:ring-gray-700 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-gray-700 dark:focus:bg-gray-200 dark:focus:text-black dark:focus:ring-0 text-sm sm:leading-6"
                                placeholder="{{ __('Search Mods') }}"
                            />
                        </search>
                    </div>

                    {{-- Results Per Page Dropdown --}}
                    <div
                        class="flex items-center border-r border-gray-400 dark:border-gray-700 flex-shrink-0 order-2 md:order-3 self-stretch">
                        <div
                            class="relative inline-block px-3 sm:px-3 lg:px-3 xl:px-4"
                            x-data="{ isResultsPerPageOpen: false }"
                            x-on:click.away="isResultsPerPageOpen = false"
                        >
                            <div class="flex">
                                <button
                                    type="button"
                                    x-on:click="isResultsPerPageOpen = !isResultsPerPageOpen"
                                    class="group inline-flex justify-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100"
                                    id="menu-button-per-page"
                                    :aria-expanded="isResultsPerPageOpen.toString()"
                                    aria-haspopup="true"
                                >
                                    <span class="hidden lg:inline">{{ __('Per Page') }}</span>
                                    <span class="lg:hidden">{{ __(':perPage/p', ['perPage' => $perPage]) }}</span>
                                    <svg
                                        class="-mr-1 ml-1 h-4 w-4 sm:h-5 sm:w-5 shrink-0 text-gray-400 group-hover:text-gray-500"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <div
                                x-cloak
                                x-show="isResultsPerPageOpen"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute top-7 right-0 z-10 flex w-full min-w-[12rem] flex-col divide-y divide-slate-300 overflow-hidden rounded-xl border border-gray-400 bg-gray-200 dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800"
                                role="menu"
                                aria-orientation="vertical"
                                aria-labelledby="menu-button-per-page"
                                tabindex="-1"
                            >
                                <div class="flex flex-col py-1.5">
                                    @foreach ($perPageOptions as $option)
                                        <x-filter-menu-item
                                            filterName="perPage"
                                            :filter="$option"
                                            :currentFilter="$perPage"
                                        >{{ $option }}</x-filter-menu-item>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Sort Dropdown --}}
                    <div class="flex items-center flex-shrink-0 order-3 md:order-4 self-stretch">
                        <div
                            class="relative inline-block px-3 sm:px-3 lg:px-3 xl:px-4"
                            x-data="{ isSortOpen: false }"
                            x-on:click.away="isSortOpen = false"
                        >
                            <div class="flex">
                                <button
                                    type="button"
                                    x-on:click="isSortOpen = !isSortOpen"
                                    class="group inline-flex justify-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100"
                                    id="menu-button-sort"
                                    :aria-expanded="isSortOpen.toString()"
                                    aria-haspopup="true"
                                >
                                    {{ __('Sort') }}
                                    <svg
                                        class="-mr-1 ml-1 h-4 w-4 sm:h-5 sm:w-5 shrink-0 text-gray-400 group-hover:text-gray-500"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <div
                                x-cloak
                                x-show="isSortOpen"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute top-7 right-0 z-10 flex w-full min-w-[12rem] flex-col divide-y divide-slate-300 overflow-hidden rounded-xl border border-gray-400 bg-gray-200 dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800"
                                role="menu"
                                aria-orientation="vertical"
                                aria-labelledby="menu-button-sort"
                                tabindex="-1"
                            >
                                <div class="flex flex-col py-1.5">
                                    <x-filter-menu-item
                                        filterName="order"
                                        filter="created"
                                        :currentFilter="$order"
                                    >{{ __('Newest') }}</x-filter-menu-item>
                                    <x-filter-menu-item
                                        filterName="order"
                                        filter="updated"
                                        :currentFilter="$order"
                                    >{{ __('Recently Updated') }}</x-filter-menu-item>
                                    <x-filter-menu-item
                                        filterName="order"
                                        filter="downloaded"
                                        :currentFilter="$order"
                                    >{{ __('Most Downloaded') }}</x-filter-menu-item>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Loading indicator - displays at all breakpoints with reserved space --}}
                    <div
                        class="hidden sm:flex items-center flex-shrink-0 order-4 md:order-5 self-stretch"
                        wire:loading.class="!flex border-l border-gray-400 dark:border-gray-700"
                    >
                        <div class="px-3 sm:px-4 lg:px-4 xl:px-6 min-w-[2.5rem] lg:min-w-[5rem] xl:min-w-[7rem]">
                            <p
                                class="flex items-center font-medium text-gray-800 dark:text-gray-300"
                                wire:loading.flex
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                    class="w-4 h-4 fill-cyan-600 dark:fill-cyan-600 motion-safe:animate-spin"
                                >
                                    <path
                                        d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z"
                                        opacity=".25"
                                    />
                                    <path
                                        d="M10.14,1.16a11,11,0,0,0-9,8.92A1.59,1.59,0,0,0,2.46,12,1.52,1.52,0,0,0,4.11,10.7a8,8,0,0,1,6.66-6.61A1.42,1.42,0,0,0,12,2.69h0A1.57,1.57,0,0,0,10.14,1.16Z"
                                    />
                                </svg>
                                <span class="pl-1.5 hidden md:inline">{{ __('Loading...') }}</span>
                            </p>
                        </div>
                    </div>

                    {{-- Force line break on extra small screens before Reset/RSS --}}
                    <div class="w-full sm:hidden order-5"></div>

                    {{-- Spacer to push Reset and RSS to the right --}}
                    <div class="hidden sm:flex flex-1 order-6 sm:order-5 md:order-6"></div>

                    {{-- Reset Filters Button --}}
                    <div class="flex items-center flex-shrink-0 order-6 sm:order-6 md:order-7 self-stretch">
                        <button
                            x-on:click="$wire.call('resetFilters')"
                            type="button"
                            class="px-3 sm:px-4 lg:px-4 xl:px-6 text-gray-600 dark:text-gray-300 hover:text-gray-700 dark:hover:text-gray-200 whitespace-nowrap text-sm"
                        >
                            {{ __('Reset Filters') }}
                        </button>
                    </div>

                    {{-- RSS Feed Link --}}
                    <div
                        class="flex items-center sm:border-l border-gray-400 dark:border-gray-700 flex-shrink-0 order-7 sm:order-7 md:order-8 self-stretch">
                        <a
                            href="{{ route('mods.rss') }}?{{ http_build_query([
                                'query' => $query,
                                'order' => $order,
                                'versions' => is_array($sptVersions) ? implode(',', $sptVersions) : $sptVersions,
                                'featured' => $featured,
                                'category' => $category,
                            ]) }}"
                            target="_blank"
                            class="flex items-center px-3 sm:px-4 lg:px-4 xl:px-6 text-gray-600 dark:text-gray-300 hover:text-gray-700 dark:hover:text-gray-200 whitespace-nowrap text-sm"
                            title="{{ __('RSS Feed for Current Filters') }}"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="w-4 h-4 sm:w-5 sm:h-5 mr-1"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12.75 19.5v-.75a7.5 7.5 0 0 0-7.5-7.5H4.5m0-6.75h.75c7.87 0 14.25 6.38 14.25 14.25v.75M6 18.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"
                                />
                            </svg>
                            {{ __('RSS') }}
                        </a>
                    </div>
                </div>
            </div>
            <div
                x-cloak
                x-show="isFilterOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform -translate-y-10"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-10"
                id="disclosure-1"
                class="py-10 border-b border-gray-400 dark:border-gray-700"
            >
                <div
                    class="mx-auto grid max-w-7xl grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-6 sm:gap-y-8 px-4 text-sm sm:px-6 md:gap-x-6 lg:px-8">
                    <div
                        class="col-span-1 sm:col-span-2 grid auto-rows-min grid-cols-1 gap-y-2 sm:gap-y-0 sm:grid-cols-2 sm:gap-x-6">
                        <!-- SPT Versions fieldset spanning both columns -->
                        <fieldset class="col-span-1 sm:col-span-2">
                            <legend class="block font-semibold text-gray-800 dark:text-gray-100">
                                {{ __('SPT Versions') }}</legend>
                            <div class="pt-6 sm:pt-4 pb-2 sm:pb-2 md:pb-1">
                                <div class="flex items-center text-base sm:text-sm">
                                    <input
                                        id="sptVersions-all"
                                        type="checkbox"
                                        wire:click="toggleVersionFilter('all')"
                                        wire:key="all-{{ md5(json_encode($sptVersions)) }}"
                                        @checked($sptVersions === 'all')
                                        class="cursor-pointer h-4 w-4 shrink-0 rounded-sm border-gray-300 text-gray-600 focus:ring-gray-500"
                                        wire:loading.attr="disabled"
                                    >
                                    <label
                                        for="sptVersions-all"
                                        class="cursor-pointer ml-3 min-w-0 inline-flex text-gray-600 dark:text-gray-300"
                                        wire:loading.class="opacity-50"
                                    >{{ __('All Versions') }}</label>
                                </div>
                            </div>
                        </fieldset>

                        <!-- First column of versions -->
                        <fieldset class="pt-4 sm:pt-4">
                            <div class="space-y-6 pt-0 sm:space-y-4">
                                @foreach ($this->splitSptVersions[0] as $version)
                                    <div class="flex items-center text-base sm:text-sm">
                                        <input
                                            id="sptVersions-{{ $version->version }}"
                                            type="checkbox"
                                            wire:click="toggleVersionFilter('{{ $version->version }}')"
                                            wire:key="{{ $version->version }}-{{ md5(json_encode($sptVersions)) }}"
                                            @checked($sptVersions !== 'all' && is_array($sptVersions) && in_array($version->version, $sptVersions))
                                            class="cursor-pointer h-4 w-4 shrink-0 rounded-sm border-gray-300 text-gray-600 focus:ring-gray-500"
                                            wire:loading.attr="disabled"
                                        >
                                        <label
                                            for="sptVersions-{{ $version->version }}"
                                            @if (auth()->user()?->isModOrAdmin() && (!$version->publish_date || $version->publish_date->isFuture())) class="cursor-pointer ml-3 min-w-0 inline-flex text-orange-600 dark:text-orange-400"
                                                title="Unpublished - Not publicly visible"
                                            @else
                                                class="cursor-pointer ml-3 min-w-0 inline-flex text-gray-600 dark:text-gray-300" @endif
                                            wire:loading.class="opacity-50"
                                        >
                                            {{ $version->version }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <!-- Second column of versions -->
                        <fieldset class="pt-4 sm:pt-4">
                            <div class="space-y-6 pt-0 sm:space-y-4">
                                @foreach ($this->splitSptVersions[1] as $version)
                                    <div class="flex items-center text-base sm:text-sm">
                                        <input
                                            id="sptVersions-{{ $version->version }}"
                                            type="checkbox"
                                            wire:click="toggleVersionFilter('{{ $version->version }}')"
                                            wire:key="{{ $version->version }}-{{ md5(json_encode($sptVersions)) }}"
                                            @checked($sptVersions !== 'all' && is_array($sptVersions) && in_array($version->version, $sptVersions))
                                            class="cursor-pointer h-4 w-4 shrink-0 rounded-sm border-gray-300 text-gray-600 focus:ring-gray-500"
                                            wire:loading.attr="disabled"
                                        >
                                        <label
                                            for="sptVersions-{{ $version->version }}"
                                            @if (auth()->user()?->isModOrAdmin() && (!$version->publish_date || $version->publish_date->isFuture())) class="cursor-pointer ml-3 min-w-0 inline-flex text-orange-600 dark:text-orange-400"
                                                title="Unpublished - Not publicly visible"
                                            @else
                                                class="cursor-pointer ml-3 min-w-0 inline-flex text-gray-600 dark:text-gray-300" @endif
                                            wire:loading.class="opacity-50"
                                        >
                                            {{ $version->version }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <!-- Legacy Versions fieldset spanning both columns -->
                        <fieldset class="col-span-1 sm:col-span-2 pt-6 sm:pt-4 md:pt-2">
                            <div class="pt-2">
                                <div class="flex items-center text-base sm:text-sm">
                                    <input
                                        id="sptVersions-legacy"
                                        type="checkbox"
                                        wire:click="toggleVersionFilter('legacy')"
                                        wire:key="legacy-{{ md5(json_encode($sptVersions)) }}"
                                        @checked(
                                            $sptVersions !== 'all' &&
                                                ((is_array($sptVersions) && in_array('legacy', $sptVersions)) || $sptVersions === 'legacy'))
                                        class="cursor-pointer h-4 w-4 shrink-0 rounded-sm border-gray-300 text-gray-600 focus:ring-gray-500"
                                        wire:loading.attr="disabled"
                                    >
                                    <label
                                        for="sptVersions-legacy"
                                        class="cursor-pointer ml-3 min-w-0 inline-flex text-gray-600 dark:text-gray-300"
                                        wire:loading.class="opacity-50"
                                    >{{ __('Legacy Versions') }}</label>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="col-span-1 md:col-span-1 mt-6 sm:mt-0 space-y-8">
                        <fieldset>
                            <legend class="block font-semibold text-gray-800 dark:text-gray-100">{{ __('Featured') }}
                            </legend>
                            <div class="space-y-6 pt-6 sm:space-y-4 sm:pt-4">
                                <x-filter-radio
                                    id="featured-0"
                                    name="featured"
                                    value="include"
                                >{{ __('Include') }}</x-filter-radio>
                                <x-filter-radio
                                    id="featured-1"
                                    name="featured"
                                    value="exclude"
                                >{{ __('Exclude') }}</x-filter-radio>
                                <x-filter-radio
                                    id="featured-2"
                                    name="featured"
                                    value="only"
                                >{{ __('Only') }}</x-filter-radio>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend class="block font-semibold text-gray-800 dark:text-gray-100">
                                {{ __('Fika Compatibility') }}</legend>
                            <div class="pt-6 sm:pt-4">
                                <div class="flex items-center text-base sm:text-sm">
                                    <input
                                        id="fikaCompatibility"
                                        type="checkbox"
                                        wire:model.live="fikaCompatibility"
                                        class="cursor-pointer h-4 w-4 shrink-0 rounded-sm border-gray-300 text-gray-600 focus:ring-gray-500"
                                        wire:loading.attr="disabled"
                                    >
                                    <label
                                        for="fikaCompatibility"
                                        class="cursor-pointer ml-3 min-w-0 inline-flex text-gray-600 dark:text-gray-300"
                                        wire:loading.class="opacity-50"
                                    >{{ __('Compatible Only') }}</label>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <fieldset class="col-span-1 md:col-span-1 mt-6 sm:mt-0">
                        <legend class="block font-semibold text-gray-800 dark:text-gray-100">{{ __('Category') }}
                        </legend>
                        <div class="pt-6 sm:pt-4">
                            <select
                                wire:model.live="category"
                                class="w-full rounded-md border-0 bg-white dark:bg-gray-700 py-1.5 pl-3 pr-10 text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-400 dark:ring-gray-700 placeholder:text-gray-500 dark:placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-gray-700 dark:focus:bg-gray-200 dark:focus:text-black dark:focus:ring-0 sm:text-sm sm:leading-6"
                                wire:loading.attr="disabled"
                            >
                                <option value="">{{ __('All Categories') }}</option>
                                @foreach ($availableCategories as $cat)
                                    <option value="{{ $cat->slug }}">{{ $cat->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </fieldset>
                </div>
            </div>
        </section>

        {{ $mods->onEachSide(1)->links() }}

        {{-- Mod Listing --}}
        @if ($mods->isNotEmpty())
            <div class="my-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach ($mods as $mod)
                    <div wire:key="mod-index-{{ $mod->id }}">
                        <x-mod.card
                            :mod="$mod"
                            :version="$mod->latestVersion"
                        />
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center text-gray-900 dark:text-gray-300">
                <p>{{ __('There were no mods found with those filters applied. ') }}</p>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="w-6 h-6 mx-auto"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15.182 16.318A4.486 4.486 0 0 0 12.016 15a4.486 4.486 0 0 0-3.198 1.318M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z"
                    />
                </svg>
            </div>
        @endif

        {{ $mods->onEachSide(1)->links() }}
    </div>
</div>
