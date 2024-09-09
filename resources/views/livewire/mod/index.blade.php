<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="px-4 py-8 sm:px-6 lg:px-8 bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 rounded-none sm:rounded-lg">
        <h1 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-200">{{ __('Mods') }}</h1>
        <p class="mt-4 text-base text-slate-500 dark:text-gray-300">{!! __('Explore an enhanced <abbr title="Single Player Tarkov">SPT</abbr> experience with the mods available below. Check out the featured mods for a tailored solo-survival game with maximum immersion.') !!}</p>

        <section x-data="{ isFilterOpen: false }"
                 @click.away="isFilterOpen = false"
                 aria-labelledby="filter-heading"
                 class="my-8 grid items-center border-t border-gray-300 dark:border-gray-700">
            <h2 id="filter-heading" class="sr-only">{{ __('Filters') }}</h2>
            <div class="relative col-start-1 row-start-1 py-4 border-b border-gray-300 dark:border-gray-700">
                <div class="mx-auto flex max-w-7xl space-x-6 divide-x divide-gray-300 dark:divide-gray-700 px-4 text-sm sm:px-6 lg:px-8">

                    <button type="button" @click="isFilterOpen = !isFilterOpen" class="group flex items-center font-medium text-gray-700 dark:text-gray-300" aria-controls="disclosure-1" aria-expanded="false">
                        <svg class="mr-2 h-5 w-5 flex-none text-gray-400 group-hover:text-gray-500 dark:text-gray-600" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z" clip-rule="evenodd" />
                        </svg>
                        {{ $this->filterCount }} {{ __('Filters') }}
                    </button>

                    <search class="relative group pl-6">
                        <div class="pointer-events-none absolute inset-y-0 left-8 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-5 w-5 text-gray-400">
                                <path fill-rule="evenodd" d="M9.965 11.026a5 5 0 1 1 1.06-1.06l2.755 2.754a.75.75 0 1 1-1.06 1.06l-2.755-2.754ZM10.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input wire:model.live="query" class="w-full rounded-md border-0 bg-white dark:bg-gray-700 py-1.5 pl-10 pr-3 text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 dark:placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-gray-600 dark:focus:bg-gray-200 dark:focus:text-black dark:focus:ring-0 sm:text-sm sm:leading-6" placeholder="{{ __('Search Mods') }}" />
                    </search>

                    <button @click="$wire.call('resetFilters')" type="button" class="pl-6 text-gray-500 dark:text-gray-300">{{ __('Reset Filters') }}</button>

                    <div wire:loading.flex>
                        <p class="pl-6 flex items-center font-medium text-gray-700 dark:text-gray-300">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" class="w-4 h-4 fill-cyan-600 dark:fill-cyan-600 motion-safe:animate-spin">
                                <path d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z" opacity=".25" />
                                <path d="M10.14,1.16a11,11,0,0,0-9,8.92A1.59,1.59,0,0,0,2.46,12,1.52,1.52,0,0,0,4.11,10.7a8,8,0,0,1,6.66-6.61A1.42,1.42,0,0,0,12,2.69h0A1.57,1.57,0,0,0,10.14,1.16Z" />
                            </svg>
                            <span class="pl-1.5">{{ __('Loading...') }}</span>
                        </p>
                    </div>
                </div>
            </div>
            <div x-cloak
                 x-show="isFilterOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform -translate-y-10"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform translate-y-0"
                 x-transition:leave-end="opacity-0 transform -translate-y-10"
                 id="disclosure-1"
                 class="py-10 border-b border-gray-300 dark:border-gray-700">
                <div class="mx-auto grid max-w-7xl grid-cols-2 gap-x-4 px-4 text-sm sm:px-6 md:gap-x-6 lg:px-8">
                    <div class="grid auto-rows-min grid-cols-1 gap-y-10 md:grid-cols-2 md:gap-x-6">
                        @php
                            $totalVersions = count($activeSptVersions);
                            $half = ceil($totalVersions / 2);
                        @endphp
                        <fieldset>
                            <legend class="block font-medium text-gray-900 dark:text-gray-100">{{ __('SPT Versions') }}</legend>
                            <div class="space-y-6 pt-6 sm:space-y-4 sm:pt-4">
                                @foreach ($activeSptVersions as $index => $version)
                                    @if ($index < $half)
                                        <x-filter-checkbox id="sptVersions-{{ $index }}" name="sptVersions" value="{{ $version->version }}">{{ $version->version }}</x-filter-checkbox>
                                    @endif
                                @endforeach
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend class="block font-medium text-gray-900 dark:text-gray-100">&nbsp;</legend>
                            <div class="space-y-6 pt-6 sm:space-y-4 sm:pt-4">
                                @foreach ($activeSptVersions as $index => $version)
                                    @if ($index >= $half)
                                        <x-filter-checkbox id="sptVersions-{{ $index }}" name="sptVersions" value="{{ $version->version }}">{{ $version->version }}</x-filter-checkbox>
                                    @endif
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                    <div class="grid auto-rows-min grid-cols-1 gap-y-10 md:grid-cols-2 md:gap-x-6">
                        <fieldset>
                            <legend class="block font-medium text-gray-900 dark:text-gray-100">{{ __('Featured') }}</legend>
                            <div class="space-y-6 pt-6 sm:space-y-4 sm:pt-4">
                                <x-filter-radio id="featured-0" name="featured" value="include">{{ __('Include') }}</x-filter-radio>
                                <x-filter-radio id="featured-1" name="featured" value="exclude">{{ __('Exclude') }}</x-filter-radio>
                                <x-filter-radio id="featured-2" name="featured" value="only">{{ __('Only') }}</x-filter-radio>
                            </div>
                        </fieldset>
                    </div>
                </div>
            </div>
            <div class="col-start-1 row-start-1 py-4">
                <div class="mx-auto flex max-w-7xl justify-end px-4 sm:px-6 lg:px-8">
                    <div class="relative inline-block" x-data="{ isSortOpen: false }" @click.away="isSortOpen = false">
                        <div class="flex">
                            <button type="button" @click="isSortOpen = !isSortOpen" class="group inline-flex justify-center text-sm font-medium text-gray-700 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100" id="menu-button" :aria-expanded="isSortOpen.toString()" aria-haspopup="true">
                                {{ __('Sort') }}
                                <svg class="-mr-1 ml-1 h-5 w-5 flex-shrink-0 text-gray-400 group-hover:text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <div x-cloak
                             x-show="isSortOpen"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute top-7 right-0 z-10 flex w-full min-w-[12rem] flex-col divide-y divide-slate-300 overflow-hidden rounded-xl border border-gray-300 bg-gray-100 dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800"
                             role="menu" aria-orientation="vertical" aria-labelledby="menu-button" tabindex="-1">
                            <div class="flex flex-col py-1.5">
                                <x-filter-sort-menu-item order="created" :currentOrder="$order">{{ __('Newest') }}</x-filter-sort-menu-item>
                                <x-filter-sort-menu-item order="updated" :currentOrder="$order">{{ __('Recently Updated') }}</x-filter-sort-menu-item>
                                <x-filter-sort-menu-item order="downloaded" :currentOrder="$order">{{ __('Most Downloaded') }}</x-filter-sort-menu-item>
                            </div>
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
                    <x-mod-card :mod="$mod" />
                @endforeach
            </div>
        @else
            <div class="text-center text-gray-700 dark:text-gray-300">
                <p>{{ __('There were no mods found with those filters applied. ') }}</p>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mx-auto">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0 0 12.016 15a4.486 4.486 0 0 0-3.198 1.318M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                </svg>
            </div>
        @endif

        {{ $mods->onEachSide(1)->links() }}
    </div>
</div>
