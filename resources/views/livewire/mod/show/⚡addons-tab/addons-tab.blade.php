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
                <div class="overflow-hidden rounded-xl bg-gray-950 shadow-md shadow-gray-950 drop-shadow-2xl">
                    <div class="p-4 sm:p-6">
                        <flux:skeleton.group animate="shimmer">
                            <div class="flex flex-col gap-3 sm:flex-row sm:gap-4">
                                {{-- Thumbnail skeleton --}}
                                <flux:skeleton
                                    class="mx-auto h-20 w-20 flex-shrink-0 rounded-lg sm:mx-0 sm:h-16 sm:w-16 md:h-20 md:w-20"
                                />

                                {{-- Content skeleton --}}
                                <div class="min-w-0 flex-1">
                                    {{-- Title --}}
                                    <div class="mb-2 flex flex-col sm:flex-row sm:items-start sm:justify-between">
                                        <flux:skeleton.line
                                            size="lg"
                                            class="mb-2 w-48 sm:mb-0"
                                        />
                                    </div>

                                    {{-- Info row --}}
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="flex-1">
                                            <flux:skeleton.line class="mb-1 w-32" />
                                            <flux:skeleton.line class="w-24" />
                                        </div>
                                        {{-- Version badges --}}
                                        <div class="sm:text-right">
                                            <flux:skeleton.line class="mb-1 w-36" />
                                            <div class="flex flex-wrap justify-center gap-1 sm:justify-end">
                                                <flux:skeleton class="h-5 w-14 rounded" />
                                                <flux:skeleton class="h-5 w-14 rounded" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Teaser skeleton --}}
                            <div class="mt-4 border-t-2 border-gray-800 pt-3">
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
                        class="whitespace-nowrap text-sm font-medium text-gray-300"
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
            <div class="rounded-xl bg-gray-950 p-4 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
                <div class="py-8 text-center">
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
        <div class="rounded-xl bg-gray-950 p-4 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
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
