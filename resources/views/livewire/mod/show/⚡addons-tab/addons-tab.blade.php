@placeholder
    <div>
        {{-- Filter bar skeleton --}}
        <div class="mb-4 flex items-center justify-between gap-4">
            <flux:skeleton.group animate="shimmer">
                <flux:skeleton.line class="w-48" />
            </flux:skeleton.group>
            <div class="flex items-center gap-3">
                <flux:skeleton.group animate="shimmer">
                    <flux:skeleton.line class="w-32" />
                    <flux:skeleton class="h-8 w-36 rounded-md" />
                </flux:skeleton.group>
            </div>
        </div>

        {{-- Addon card skeletons --}}
        <div class="grid gap-4">
            @for ($i = 0; $i < 3; $i++)
                <div
                    class="bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl overflow-hidden">
                    <div class="p-4 sm:p-6">
                        <flux:skeleton.group animate="shimmer">
                            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                                {{-- Thumbnail skeleton --}}
                                <flux:skeleton
                                    class="w-20 h-20 sm:w-16 sm:h-16 md:w-20 md:h-20 rounded-lg flex-shrink-0 mx-auto sm:mx-0"
                                />

                                {{-- Content skeleton --}}
                                <div class="flex-1 min-w-0">
                                    {{-- Title --}}
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-2">
                                        <flux:skeleton.line
                                            size="lg"
                                            class="w-48 mb-2 sm:mb-0"
                                        />
                                    </div>

                                    {{-- Info row --}}
                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
                                        <div class="flex-1">
                                            <flux:skeleton.line class="w-32 mb-1" />
                                            <flux:skeleton.line class="w-24" />
                                        </div>
                                        {{-- Version badges --}}
                                        <div class="sm:text-right">
                                            <flux:skeleton.line class="w-36 mb-1" />
                                            <div class="flex flex-wrap gap-1 justify-center sm:justify-end">
                                                <flux:skeleton class="h-5 w-14 rounded" />
                                                <flux:skeleton class="h-5 w-14 rounded" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Teaser skeleton --}}
                            <div class="mt-4 pt-3 border-t-2 border-gray-800">
                                <flux:skeleton.line class="w-full" />
                                <flux:skeleton.line class="w-3/4" />
                            </div>
                        </flux:skeleton.group>
                    </div>
                </div>
            @endfor
        </div>
    </div>
@endplaceholder

<div>
    @if ($this->mod->addons_enabled)
        @if ($this->addonCount > 0)
            {{-- Version Filter --}}
            <div class="mb-4 flex items-center justify-between gap-4">
                <div class="text-sm text-gray-400">
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
                        class="text-sm font-medium text-gray-300 whitespace-nowrap"
                    >
                        Filter by mod version:
                    </label>
                    <flux:select
                        variant="listbox"
                        wire:model.live="selectedModVersionId"
                        id="mod-version-filter"
                        size="sm"
                    >
                        <flux:select.option value="">All versions</flux:select.option>
                        @foreach ($this->modVersionsForFilter as $version)
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
                @foreach ($this->addons as $addon)
                    <x-addon.card
                        :addon="$addon"
                        :selected-mod-version-id="$selectedModVersionId"
                        wire:key="addon-card-{{ $addon->id }}"
                    />
                @endforeach
            </div>
            {{ $this->addons->links() }}
        @else
            <div class="p-4 sm:p-6 bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl">
                <div class="text-center py-8">
                    <flux:icon.puzzle-piece class="mx-auto size-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-100">
                        {{ __('No Addons Yet') }}</h3>
                    <p class="mt-1 text-sm text-gray-400">
                        {{ __('This mod doesn\'t have any addons yet.') }}</p>
                    @cachedCan('create', [App\Models\Addon::class, $this->mod])
                        <div class="mt-6">
                            <flux:button href="{{ route('addon.guidelines', ['mod' => $this->mod->id]) }}">
                                {{ __('Create First Addon') }}
                            </flux:button>
                        </div>
                    @endcachedCan
                </div>
            </div>
        @endif
    @else
        <div class="p-4 sm:p-6 bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl">
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