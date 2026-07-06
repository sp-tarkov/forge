<x-slot:title>
    {!! __('Recently Created Mods - The Forge') !!}
</x-slot>

<x-slot:description>
    {!! __('See which mods have been newly created. Discover the latest additions to the modding community.') !!}
</x-slot>

<x-slot:header>
    <div class="flex w-full items-center justify-between">
        <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-gray-200">
            <flux:icon.sparkles class="h-5 w-5" />
            {{ __('Recently Created Mods') }}
        </h2>
        <div class="flex items-center gap-2">
            <flux:button
                x-data
                x-on:click="Livewire.dispatch('mark-created-as-read')"
                size="sm"
                variant="primary"
                icon="check"
            >{{ __('Mark as Read') }}</flux:button>
            <flux:button
                href="{{ route('mods') }}"
                wire:navigate
                size="sm"
            >{{ __('Browse All Mods') }}</flux:button>
        </div>
    </div>
</x-slot>

<div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
    <div
        class="overflow-hidden rounded-none bg-gray-900 px-4 py-8 shadow-xl shadow-gray-900 sm:rounded-lg sm:px-6 lg:px-8">
        <h1 class="text-4xl font-bold tracking-tight text-gray-200">{{ __('Recently Created') }}</h1>
        <p class="mt-4 text-base text-gray-300">
            @if ($previousViewedAt)
                {!! __(
                    'Mods that have been created since your last visit. Check back regularly to discover new additions to the community.',
                ) !!}
            @else
                {!! __(
                    'This is your first visit! All recently created mods are shown below. On your next visit, you\'ll only see mods created since now.',
                ) !!}
            @endif
        </p>

        <section
            aria-labelledby="options-heading"
            class="my-8 grid items-center border-t border-gray-700"
        >
            <h2
                id="options-heading"
                class="sr-only"
            >{{ __('Options') }}</h2>
            <div class="relative col-start-1 row-start-1 border-b border-gray-700 py-4">
                <div
                    class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-2 text-sm sm:justify-start lg:flex-nowrap lg:gap-0">
                    {{-- Results Per Page Dropdown --}}
                    <div class="order-1 flex flex-shrink-0 items-center self-stretch border-r border-gray-700">
                        <div
                            class="relative inline-block px-3 sm:px-3 lg:px-3 xl:px-4"
                            x-data="{ isResultsPerPageOpen: false }"
                            x-on:click.away="isResultsPerPageOpen = false"
                        >
                            <div class="flex">
                                <button
                                    type="button"
                                    x-on:click="isResultsPerPageOpen = !isResultsPerPageOpen"
                                    class="group inline-flex justify-center text-sm font-medium text-gray-300 hover:text-gray-100"
                                    id="menu-button-per-page"
                                    :aria-expanded="isResultsPerPageOpen.toString()"
                                    aria-haspopup="true"
                                >
                                    <span class="hidden lg:inline">{{ __('Per Page') }}</span>
                                    <span class="lg:hidden">{{ __(':perPage/p', ['perPage' => $perPage]) }}</span>
                                    <svg
                                        class="-mr-1 ml-1 h-4 w-4 shrink-0 text-gray-400 group-hover:text-gray-500 sm:h-5 sm:w-5"
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
                                class="absolute left-0 top-7 z-10 flex w-full min-w-[12rem] flex-col divide-y divide-gray-700 overflow-hidden rounded-xl border border-gray-700 bg-gray-800"
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
                        class="order-2 hidden flex-shrink-0 items-center self-stretch sm:flex"
                        wire:loading.class="!flex border-l border-gray-700"
                    >
                        <div class="min-w-[2.5rem] px-3 sm:px-4 lg:min-w-[5rem] lg:px-4 xl:min-w-[7rem] xl:px-6">
                            <p
                                class="flex items-center font-medium text-gray-300"
                                wire:loading.flex
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                    class="h-4 w-4 fill-cyan-600 motion-safe:animate-spin"
                                >
                                    <path
                                        d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z"
                                        opacity=".25"
                                    />
                                    <path
                                        d="M10.14,1.16a11,11,0,0,0-9,8.92A1.59,1.59,0,0,0,2.46,12,1.52,1.52,0,0,0,4.11,10.7a8,8,0,0,1,6.66-6.61A1.42,1.42,0,0,0,12,2.69h0A1.57,1.57,0,0,0,10.14,1.16Z"
                                    />
                                </svg>
                                <span class="hidden pl-1.5 md:inline">{{ __('Loading...') }}</span>
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
                    <div wire:key="mod-recently-created-{{ $mod->id }}">
                        <x-mod.card
                            :mod="$mod"
                            :version="$mod->latestVersion"
                        />
                    </div>
                @endforeach
            </div>
        @else
            <div class="my-8 text-center text-gray-300">
                <flux:icon.check-circle class="mx-auto mb-4 h-12 w-12 text-green-500" />
                <p class="text-lg font-medium">{{ __('You\'re all caught up!') }}</p>
                <p class="mt-2 text-sm text-gray-400">
                    {{ __('No new mods have been created since your last visit.') }}</p>
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
