<div>
    @if ($mod->addons_enabled)
        @if ($addonCount > 0)
            {{-- Version Filter --}}
            <div class="mb-4 flex items-center justify-between gap-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <span x-show="!$wire.selectedModVersionId">
                        Select a mod version to filter by on the right.
                    </span>
                    <span
                        x-show="$wire.selectedModVersionId"
                        x-cloak
                    >
                        Showing addons compatible with selected version.
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <label
                        for="mod-version-filter"
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"
                    >
                        Filter by mod version:
                    </label>
                    <flux:select
                        wire:model.live="selectedModVersionId"
                        id="mod-version-filter"
                        size="sm"
                    >
                        <flux:select.option value="">All versions</flux:select.option>
                        @foreach ($modVersionsForFilter as $version)
                            <flux:select.option value="{{ $version->id }}">
                                v{{ $version->version }}
                                @if ($version->latestSptVersion)
                                    ({{ $version->latestSptVersion->version_formatted }})
                                @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="grid gap-4">
                @foreach ($addons as $addon)
                    <x-addon.card
                        :addon="$addon"
                        :selected-mod-version-id="$selectedModVersionId"
                        wire:key="addon-card-{{ $addon->id }}"
                    />
                @endforeach
            </div>
            {{ $addons->links() }}
        @else
            <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <div class="text-center py-8">
                    <flux:icon.puzzle-piece class="mx-auto size-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('No Addons Yet') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('This mod doesn\'t have any addons yet.') }}</p>
                    @cachedCan('create', [App\Models\Addon::class, $mod])
                        <div class="mt-6">
                            <flux:button href="{{ route('addon.guidelines', ['mod' => $mod->id]) }}">
                                {{ __('Create First Addon') }}
                            </flux:button>
                        </div>
                    @endcachedCan
                </div>
            </div>
        @endif
    @else
        <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <flux:callout
                icon="information-circle"
                color="zinc"
            >
                <flux:callout.heading>{{ __('Addons Disabled') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('The mod owner has disabled addons for this mod.') }}
                </flux:callout.text>
            </flux:callout>
        </div>
    @endif
</div>
