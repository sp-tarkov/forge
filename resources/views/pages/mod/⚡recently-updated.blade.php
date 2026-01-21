<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Traits\Livewire\ModeratesMod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // Capture the previous timestamp before updating (used for filtering)
        $this->previousViewedAt = $user->mods_updated_viewed_at?->toISOString();

        // Update the user's last viewed timestamp
        $user->update(['mods_updated_viewed_at' => now()]);
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

        $this->perPage = $allowed->sortBy(fn(int $item): int => abs($item - $value))->first();
    }

    /**
     * Return data for the view.
     *
     * @return array<string, LengthAwarePaginator<int, Mod>>
     */
    public function with(): array
    {
        $showDisabled = auth()->user()?->isModOrAdmin() ?? false;
        $previousViewedAt = $this->previousViewedAt ? Carbon::parse($this->previousViewedAt) : null;

        $mods = Mod::query()
            ->select('mods.*')
            ->unless($showDisabled, fn(Builder $query) => $query->where('mods.disabled', false))
            ->whereExists(function (QueryBuilder $query) use ($showDisabled): void {
                $query->select(DB::raw(1))->from('mod_versions')->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')->whereColumn('mod_versions.mod_id', 'mods.id')->unless($showDisabled, fn(QueryBuilder $query) => $query->where('mod_versions.disabled', false))->unless($showDisabled, fn(QueryBuilder $query) => $query->whereNotNull('mod_versions.published_at'))->unless($showDisabled, fn(QueryBuilder $query) => $query->whereNotNull('spt_versions.publish_date')->where('spt_versions.publish_date', '<=', now()));
            })
            // Filter to only show mods updated since the user's last visit (if they've visited before)
            ->when(
                $previousViewedAt,
                fn(Builder $query) => $query->whereHas('latestUpdatedVersion', function (Builder $q) use ($previousViewedAt): void {
                    $q->where('created_at', '>', $previousViewedAt);
                }),
            )
            ->leftJoin('mod_versions as latest_versions', function (JoinClause $join) use ($showDisabled): void {
                $join
                    ->on('latest_versions.mod_id', '=', 'mods.id')
                    ->whereNotNull('latest_versions.published_at')
                    ->where('latest_versions.disabled', false)
                    ->whereExists(function (QueryBuilder $query) use ($showDisabled): void {
                        $query->select(DB::raw(1))->from('mod_version_spt_version')->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')->whereColumn('mod_version_spt_version.mod_version_id', 'latest_versions.id')->unless($showDisabled, fn(QueryBuilder $q) => $q->whereNotNull('spt_versions.publish_date')->where('spt_versions.publish_date', '<=', now()));
                    })
                    ->where('latest_versions.created_at', '=', function (QueryBuilder $query): void {
                        $query->select(DB::raw('MAX(mv2.created_at)'))->from('mod_versions as mv2')->whereColumn('mv2.mod_id', 'mods.id')->whereNotNull('mv2.published_at')->where('mv2.disabled', false);
                    });
            })
            ->latest('latest_versions.created_at')
            ->paginate($this->perPage);

        $this->redirectOutOfBoundsPage($mods);

        return ['mods' => $mods];
    }

    /**
     * Check if the current page is greater than the last page. Redirect if it is.
     *
     * @param LengthAwarePaginator<int, Mod> $paginatedMods
     */
    private function redirectOutOfBoundsPage(LengthAwarePaginator $paginatedMods): void
    {
        if ($paginatedMods->currentPage() > $paginatedMods->lastPage()) {
            $this->redirectRoute('mods.recently-updated', ['page' => $paginatedMods->lastPage()]);
        }
    }
}; ?>

<x-slot:title>
    {!! __('Recently Updated Mods - The Forge') !!}
</x-slot>

<x-slot:description>
    {!! __(
        'See which mods have been recently updated with new versions. Stay up to date with the latest mod improvements and features.',
    ) !!}
</x-slot>

<x-slot:header>
    <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
            <flux:icon.arrow-path class="w-5 h-5" />
            {{ __('Recently Updated Mods') }}
        </h2>
        <flux:button
            href="{{ route('mods') }}"
            wire:navigate
            size="sm"
        >{{ __('Browse All Mods') }}</flux:button>
    </div>
</x-slot>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div
        class="px-4 py-8 sm:px-6 lg:px-8 bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 rounded-none sm:rounded-lg">
        <h1 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-200">{{ __('Recently Updated') }}</h1>
        <p class="mt-4 text-base text-gray-800 dark:text-gray-300">
            @if ($previousViewedAt)
                {!! __(
                    'Mods that have been updated since your last visit. Check back regularly to stay up to date with the latest improvements.',
                ) !!}
            @else
                {!! __(
                    'This is your first visit! All recently updated mods are shown below. On your next visit, you\'ll only see mods updated since now.',
                ) !!}
            @endif
        </p>

        <section
            aria-labelledby="options-heading"
            class="my-8 grid items-center border-t border-gray-400 dark:border-gray-700"
        >
            <h2
                id="options-heading"
                class="sr-only"
            >{{ __('Options') }}</h2>
            <div class="relative col-start-1 row-start-1 py-4 border-b border-gray-400 dark:border-gray-700">
                <div
                    class="mx-auto flex flex-wrap items-center justify-center sm:justify-start gap-2 lg:gap-0 lg:flex-nowrap max-w-7xl text-sm">
                    {{-- Results Per Page Dropdown --}}
                    <div
                        class="flex items-center border-r border-gray-400 dark:border-gray-700 flex-shrink-0 order-1 self-stretch">
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
                                class="absolute top-7 left-0 z-10 flex w-full min-w-[12rem] flex-col divide-y divide-slate-300 overflow-hidden rounded-xl border border-gray-400 bg-gray-200 dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800"
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

                    {{-- Loading indicator --}}
                    <div
                        class="hidden sm:flex items-center flex-shrink-0 order-2 self-stretch"
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
                </div>
            </div>
        </section>

        {{ $mods->onEachSide(1)->links() }}

        {{-- Mod Listing --}}
        @if ($mods->isNotEmpty())
            <div class="my-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach ($mods as $mod)
                    <div wire:key="mod-recently-updated-{{ $mod->id }}">
                        <x-mod.card
                            :mod="$mod"
                            :version="$mod->latestVersion"
                        />
                    </div>
                @endforeach
            </div>
        @else
            <div class="my-8 text-center text-gray-900 dark:text-gray-300">
                <flux:icon.check-circle class="w-12 h-12 mx-auto mb-4 text-green-500" />
                <p class="text-lg font-medium">{{ __('You\'re all caught up!') }}</p>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('No mods have been updated since your last visit.') }}</p>
                <flux:button
                    href="{{ route('mods') }}"
                    wire:navigate
                    variant="primary"
                    class="mt-4"
                >{{ __('Browse All Mods') }}</flux:button>
            </div>
        @endif

        {{ $mods->onEachSide(1)->links() }}
    </div>
</div>
