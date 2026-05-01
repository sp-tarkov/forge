<x-slot:title>
    {{ __('Mod Lists - The Forge') }}
</x-slot>

<x-slot:description>
    {{ __('Discover user-curated mod lists. Browse collections of mods grouped by theme, compatibility, or personal taste.') }}
</x-slot>

<x-slot:header>
    <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
            <flux:icon.list-bullet class="w-5 h-5" />
            {{ __('Mod Lists') }}
        </h2>
        @auth
            <flux:button
                size="sm"
                :href="route('list.create')"
                wire:navigate
            >
                {{ __('Create New List') }}
            </flux:button>
        @endauth
    </div>
</x-slot>

<div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
    <div class="px-4 py-8 sm:px-6 lg:px-8 bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 rounded-none sm:rounded-lg space-y-6">
        <div>
            <h1 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-200">{{ __('Mod Lists') }}</h1>
            <p class="mt-4 text-base text-gray-800 dark:text-gray-300">
                {{ __('Collections of mods grouped together by other community members.') }}
            </p>
        </div>

        <flux:callout
            icon="information-circle"
            color="sky"
            inline
        >
            <flux:callout.heading>{{ __('About Mod Lists') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Mod lists are user-curated collections, not officially tested combinations. Always read each mod\'s page for installation and compatibility notes. If mods in a list don\'t play well together, it\'s not the responsibility of the individual mod authors to fix.') }}
            </flux:callout.text>
        </flux:callout>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    :placeholder="__('Search lists by title…')"
                    label:sr-only="{{ __('Search lists') }}"
                />
            </div>
            <div>
                <flux:select
                    wire:model.live="sptVersionId"
                    variant="listbox"
                    :placeholder="__('All SPT versions')"
                    label:sr-only="{{ __('SPT version') }}"
                >
                    <flux:select.option value="">{{ __('All SPT versions') }}</flux:select.option>
                    @foreach ($this->sptVersionOptions as $version)
                        <flux:select.option value="{{ $version->id }}">
                            {{ $version->version }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        @if ($search !== '' || $sptVersionId !== null)
            <div class="mt-3 flex justify-end">
                <flux:button
                    variant="ghost"
                    size="sm"
                    wire:click="clearFilters"
                >
                    {{ __('Clear filters') }}
                </flux:button>
            </div>
        @endif
    </div>

    @if ($this->lists->total() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($this->lists as $list)
                <a
                    wire:key="list-index-card-{{ $list->id }}"
                    href="{{ $list->detailUrl() }}"
                    wire:navigate
                    class="flex flex-col overflow-hidden bg-white dark:bg-gray-900 rounded-xl shadow-md dark:shadow-gray-900 drop-shadow-2xl hover:bg-gray-50 dark:hover:bg-black"
                >
                    @if ($list->thumbnail)
                        <img
                            src="{{ $list->thumbnailUrl }}"
                            alt="{{ $list->title }}"
                            class="aspect-[16/9] w-full object-cover"
                        >
                    @else
                        <div class="aspect-[16/9] w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 flex items-center justify-center">
                            <flux:icon.list-bullet class="size-10 text-gray-400" />
                        </div>
                    @endif
                    <div class="flex flex-col flex-1 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $list->title }}
                        </h2>
                        @if ($list->sptVersion)
                            <span
                                class="badge-version {{ $list->sptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium whitespace-nowrap"
                            >
                                {{ $list->sptVersion->version }}
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('by :owner', ['owner' => $list->owner?->name ?? __('Unknown')]) }}
                    </p>
                    @if ($list->description)
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300 line-clamp-3">
                            {{ Str::limit(strip_tags($list->description), 160) }}
                        </p>
                    @endif
                    <div class="mt-auto pt-3 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <flux:icon.list-bullet class="size-4" />
                        <span>{{ $list->items_count }} {{ __(Str::plural('item', $list->items_count)) }}</span>
                        <span aria-hidden="true">·</span>
                        <x-time :datetime="$list->updated_at" />
                    </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div>
            {{ $this->lists->links() }}
        </div>
    @else
        <div class="p-8 bg-white dark:bg-gray-900 rounded-xl shadow-md dark:shadow-gray-900 drop-shadow-2xl text-center">
            <flux:icon.list-bullet class="mx-auto size-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                {{ __('No lists match your filters') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Try broadening your search or clearing filters.') }}
            </p>
        </div>
    @endif
</div>
