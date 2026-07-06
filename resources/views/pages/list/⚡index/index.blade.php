<x-slot:title>
    {{ __('Mod Lists - The Forge') }}
</x-slot>

<x-slot:description>
    {{ __('Discover user-curated mod lists. Browse collections of mods grouped by theme, compatibility, or personal taste.') }}
</x-slot>

<x-slot:header>
    <div class="flex w-full items-center justify-between">
        <div class="flex items-center gap-2 text-xl font-semibold leading-tight text-gray-200">
            <flux:icon.list-bullet class="h-5 w-5" />
            {{ __('Mod Lists') }}
        </div>
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

<div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
    <div
        class="space-y-6 overflow-hidden rounded-none bg-gray-900 px-4 py-8 shadow-xl shadow-gray-900 sm:rounded-lg sm:px-6 lg:px-8">
        <div>
            <h1 class="text-4xl font-bold tracking-tight text-gray-200">{{ __('Mod Lists') }}</h1>
            <p class="mt-4 text-base text-gray-300">
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

        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
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
        <div class="grid grid-cols-1 gap-4 px-4 sm:grid-cols-2 sm:px-0 lg:grid-cols-3">
            @foreach ($this->lists as $list)
                <x-list.card
                    wire:key="list-index-card-{{ $list->id }}"
                    :list="$list"
                    :show-owner="true"
                />
            @endforeach
        </div>
        <div class="px-4 sm:px-0">
            {{ $this->lists->links() }}
        </div>
    @else
        <div class="mx-4 rounded-xl bg-gray-900 p-8 text-center shadow-md shadow-gray-900 drop-shadow-2xl sm:mx-0">
            <flux:icon.list-bullet class="mx-auto size-12 text-gray-400" />
            <h2 class="mt-2 text-sm font-semibold text-gray-100">
                {{ __('No lists match your filters') }}
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                {{ __('Try broadening your search or clearing filters.') }}
            </p>
        </div>
    @endif
</div>
