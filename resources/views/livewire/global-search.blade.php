<div class="relative z-10" role="dialog" aria-modal="true">
    <div x-cloak x-show="searchOpen" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-900 dark:bg-gray-400 bg-opacity-80 dark:bg-opacity-80 transition-opacity"></div>
    <div x-cloak x-show="searchOpen" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @keyup.escape.window="searchOpen = false" class="fixed inset-0 z-10 w-screen overflow-y-auto p-4 sm:p-6 md:p-20">
        <div @click.outside="searchOpen = false" class="mx-auto max-w-2xl transform divide-y divide-gray-100 dark:divide-gray-500 overflow-hidden rounded-xl bg-white dark:bg-gray-900 shadow-2xl ring-1 ring-black ring-opacity-5 transition-all">
            <div class="relative">
                <svg class="pointer-events-none absolute left-4 top-3.5 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
                </svg>
                <input wire:model.live="query" id="global-search" type="text" class="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-gray-900 dark:text-white placeholder:text-gray-400 focus:ring-0 sm:text-sm" placeholder="{{ __('Search for a mod...') }}">
            </div>

            @if($results->count() && $this->query)
                <ul class="max-h-80 scroll-py-2 divide-y divide-gray-100 dark:divide-gray-500 overflow-y-auto">
                    <h2 class="sr-only">{{ __('Search Results') }}</h2>
                    @foreach($results as $result)
                        <li class="text-sm group">
                            <a href="/mod/{{ $result->id }}/{{ $result->slug }}" class="block w-full group flex select-none items-center rounded-md p-3 text-gray-700 hover:text-black focus:text-black dark:text-gray-400 dark:hover:text-white">
                                @if(empty($result->thumbnail))
                                    <img src="https://placehold.co/450x450/EEE/31343C?font=source-sans-pro&text={{ $result->name }}" alt="{{ $result->name }}" class="block dark:hidden h-6 w-6 flex-none">
                                    <img src="https://placehold.co/450x450/31343C/EEE?font=source-sans-pro&text={{ $result->name }}" alt="{{ $result->name }}" class="hidden dark:block h-6 w-6 flex-none">
                                @else
                                    <img src="{{ Storage::url($result->thumbnail) }}" alt="{{ $result->name }}" class="h-6 w-6 flex-none">
                                @endif
                                <span class="ml-3 flex-auto truncate">{{ $result->name }}</span>
                                <span class="ml-3 flex-none text-xs font-semibold text-gray-400">Mod</span> </a>
                        </li>
                    @endforeach
                </ul>
            @elseif(!$results->count() && $this->query)
                <div class="px-6 py-14 text-center sm:px-14">
                    <svg class="mx-auto h-6 w-6 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Zm3.75 11.625a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                    </svg>
                    <p class="mt-4 text-sm text-gray-900 dark:text-gray-200">{{ __("We couldn't find any content with that query. Please try again.") }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
