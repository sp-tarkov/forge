<div>
    {{--
    TODO:
    [X] search bar for mods
    [X] spt version filter
        - ratings not in yet, otherwise ready
    [ ] tags filter
    [ ] small / mobile display handling
    [ ] light mode theme handling
    --}}

    {{-- page links --}}
    <div class="m-6">
        {{ $mods->links() }}
    </div>

    {{-- grid layout --}}
    <div class="grid gap-6 grid-cols-1 lg:grid-cols-4 m-4">

        {{-- search / section filters, mods --}}
        <div class="col-span-3">
            {{-- mods serach bar --}}
            <div>
                <search class="relative group">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor"><path d="M771-593 630-734l-85 84-85-84 113-114q12-12 27-17.5t30-5.5q16 0 30.5 5.5T686-848l85 85q18 17 26.5 39.5T806-678q0 23-8.5 45T771-593ZM220-409q-18-18-18-42.5t18-42.5l98-99 85 85-99 99q-17 18-41.5 18T220-409Zm-43 297q-11-12-17-26.5t-6-30.5q0-16 5.5-30.5T177-226l283-282-127-128q-18-17-18-41.5t18-42.5q17-18 42-18t43 18l127 127 57-57 112 114q12 12 12 28t-12 28q-12 12-28 12t-28-12L290-112q-12 12-26.5 17.5T234-89q-15 0-30-6t-27-17Z"/></svg>
                    </div>
                    <input wire:model.live="modSearch" class="w-1/3 rounded-md border-0 bg-white dark:bg-gray-700 py-1.5 pl-10 pr-3 text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 dark:placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-gray-600 dark:focus:bg-gray-200 dark:focus:text-black dark:focus:ring-0 sm:text-sm sm:leading-6" placeholder="Search Mods ..." />
                </search>
            </div>

            {{-- section filters --}}
            <div class="hidden sm:block my-4">
                <nav class="isolate flex divide-x divide-gray-200 dark:divide-gray-800 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl" aria-label="Tabs">
                    <button wire:click="changeSection('featured')" class="tab rounded-l-xl group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-black dark:hover:text-white focus:z-10">
                        <span>{{ __('Featured') }}</span>
                        <span aria-hidden="true" class="{{ $sectionFilter === 'featured' ? 'bg-gray-500 absolute inset-x-0 bottom-0 h-0.5' : 'bottom-0 h-0.5' }}"></span>
                    </button>
                    <button wire:click="changeSection('new')" class="tab group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-black dark:hover:text-white focus:z-10">
                        <span>{{ __('New') }}</span>
                        <span aria-hidden="true" class="{{ $sectionFilter === 'new' ? 'bg-gray-500 absolute inset-x-0 bottom-0 h-0.5' : 'bottom-0 h-0.5' }}"></span>
                    </button>
                    <button wire:click="changeSection('recently_updated')" class="tab group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-black dark:hover:text-white focus:z-10">
                        <span>{{ __('Recently Updated') }}</span>
                        <span aria-hidden="true" class="{{ $sectionFilter === 'recently_updated' ? 'bg-gray-500 absolute inset-x-0 bottom-0 h-0.5' : 'bottom-0 h-0.5' }}"></span>
                    </button>
                    <button wire:click="changeSection('most_downloaded')" class="tab group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-black dark:hover:text-white focus:z-10">
                        <span>{{ __('Most Downloaded') }}</span>
                        <span aria-hidden="true" class="{{ $sectionFilter === 'most_downloaded' ? 'bg-gray-500 absolute inset-x-0 bottom-0 h-0.5' : 'bottom-0 h-0.5' }}"></span>
                    </button>
                    <button wire:click="changeSection('top_rated')" class="tab rounded-r-xl group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-black dark:hover:text-white focus:z-10">
                        <span>{{ __('Top Rated') }}</span>
                        <span aria-hidden="true" class="{{ $sectionFilter === 'top_rated' ? 'bg-gray-500 absolute inset-x-0 bottom-0 h-0.5' : 'bottom-0 h-0.5' }}"></span>
                    </button>
                </nav>
            </div>

            {{-- mod cards --}}
            <div class="grid gap-6 grid-cols-2">
                @foreach($mods as $mod)
                    <x-mod-card :mod="$mod" />
                @endforeach
            </div>
        </div>

        {{-- version filters, tags --}}
        <div class="flex flex-col col-span-1 gap-4">
            {{-- spt version filters --}}
            <div class="flex flex-col text-gray-700 bg-gray-300 dark:text-gray-200 dark:bg-gray-950 p-4 rounded-xl">
                <h2>SPT Version</h2>
                <select class="rounded-md dark:text-white bg-gray-100 dark:bg-gray-950 border-gray-300 dark:border-gray-700 focus:border-grey-500 dark:focus:border-grey-600 focus:ring-grey-500 dark:focus:ring-grey-600">
                    <option>All</option>
                    <option>Some</option>
                    <option>Other</option>
                    <option>Blah</option>
                </select>
            </div>

            {{-- tag filters --}}
            <div class="flex flex-col text-gray-700 bg-gray-300 dark:text-gray-200 dark:bg-gray-950 p-4 rounded-xl gap-2">
                <h2>Tags</h2>
                <button class="flex justify-between bg-gray-700 rounded-md border-gray-200 p-2">
                    <span>Placeholder</span>
                    <span>2501</span>
                </button>
                <button class="flex justify-between bg-gray-700 rounded-md border-gray-200 p-2">
                    <span>Stuff</span>
                    <span>420</span>
                </button>
                <button class="flex justify-between bg-gray-700 rounded-md border-gray-200 p-2">
                    <span>Here</span>
                    <span>69</span>
                </button>
            </div>
        </div>
    </div>
    {{-- page links --}}
    <div class="m-6">
        {{ $mods->links() }}
    </div>


</div>

