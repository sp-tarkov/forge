<div class="relative z-10" role="dialog" aria-modal="true">
    <div
        x-cloak
        x-show="searchOpen"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-500 bg-opacity-50 transition-opacity"
    ></div>

    <div
        x-cloak
        x-show="searchOpen"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @keyup.escape.window="searchOpen = false"
        class="fixed inset-0 z-10 w-screen overflow-y-auto p-4 sm:p-6 md:p-20"
    >
        <div
            @click.outside="searchOpen = false"
            class="mx-auto max-w-2xl transform divide-y divide-gray-100 overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black ring-opacity-5 transition-all"
        >
            <div class="relative">
                <svg class="pointer-events-none absolute left-4 top-3.5 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                </svg>
                <input wire:model.live="query" id="global-search" type="text" class="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm" placeholder="{{ __('Search for a mod...') }}">
            </div>

            <!-- Default state, show/hide based on command palette state. -->
            @if($results)
                <ul class="max-h-80 scroll-py-2 divide-y divide-gray-100 overflow-y-auto">
                    @foreach($results as $result)
                        <li class="p-2">
                            <h2 class="sr-only">{{ __('Search Results') }}</h2>
                            <ul class="text-sm text-gray-700">
                                <!-- Active: "bg-indigo-600 text-white" -->
                                <li class="group flex cursor-default select-none items-center rounded-md px-3 py-2">
                                    <!-- Active: "text-white", Not Active: "text-gray-400" -->
                                    <img src="{{ $result->thumbnail }}" alt="{{ $result->name }}" class="h-6 w-6 flex-none">
                                    <span class="ml-3 flex-auto truncate"><a href="/mod/{{ $result->id }}/{{ $result->slug }}">{{ $result->name }}</a></span>
                                    <!-- Active: "text-indigo-100", Not Active: "text-gray-400" -->
                                    <span class="ml-3 flex-none text-xs font-semibold text-gray-400">Mod</span>
                                </li>
                            </ul>
                        </li>
                    @endforeach
                </ul>
            @else
                <!-- Empty state, show/hide based on command palette state. -->
                <div class="px-6 py-14 text-center sm:px-14">
                    <svg class="mx-auto h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                    </svg>
                    <p class="mt-4 text-sm text-gray-900">{{ __('We couldn\'t find any projects with that term. Please try again.') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
