<div {{ $attributes->merge(['class' => 'addon-list-component relative']) }}>

    <livewire:ribbon.addon
        wire:key="addon-card-ribbon-{{ $addon->id }}"
        :addon-id="$addon->id"
        :disabled="$addon->disabled"
        :published-at="$addon->published_at?->toISOString()"
        :publicly-visible="$addon->isPubliclyVisible()"
    />

    <div
        class="group overflow-hidden rounded-xl bg-gray-950 shadow-md shadow-gray-950 drop-shadow-2xl hover:bg-black hover:shadow-lg">
        {{-- Detached indicator if applicable --}}
        @if ($addon->isDetached() && auth()->user()?->isModOrAdmin())
            <div class="border-b border-yellow-800 bg-yellow-900/20 px-3 py-2 sm:px-4">
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon.exclamation-circle class="size-4 text-yellow-400" />
                    <span class="font-medium text-yellow-300">Detached Addon</span>
                </div>
            </div>
        @endif

        <div class="p-4 sm:p-6">
            <div class="flex flex-row gap-3 sm:gap-4">
                {{-- Thumbnail with striped background --}}
                <div class="relative flex-shrink-0 rounded-lg">
                    {{-- Default stripe background --}}
                    <div
                        class="absolute inset-0 rounded-lg bg-[repeating-linear-gradient(45deg,#020509,#020509_4px,#030712_4px,#030712_8px)] transition-opacity duration-200 group-hover:opacity-0">
                    </div>
                    {{-- Hover stripe background --}}
                    <div
                        class="absolute inset-0 rounded-lg bg-[repeating-linear-gradient(45deg,#000000,#000000_4px,#010203_4px,#010203_8px)] opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                    </div>
                    {{-- Thumbnail content --}}
                    <a
                        href="{{ route('addon.show', [$addon->id, $addon->slug]) }}"
                        wire:navigate
                        class="relative block overflow-hidden rounded-t-lg"
                    >
                        @if ($addon->thumbnail)
                            <img
                                src="{{ $addon->thumbnailUrl }}"
                                alt="{{ $addon->name }}"
                                class="size-20 transform object-cover transition-transform duration-200 group-hover:scale-105"
                            >
                        @else
                            <div class="flex size-20 items-center justify-center">
                                <flux:icon.puzzle-piece class="size-10 text-gray-600" />
                            </div>
                        @endif
                    </a>
                </div>

                {{-- Content - centered text on mobile, left-aligned on desktop --}}
                <div class="min-w-0 flex-1">
                    {{-- Header with Title and Version --}}
                    <div class="mb-2 flex flex-col sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <a
                                href="{{ route('addon.show', [$addon->id, $addon->slug]) }}"
                                wire:navigate
                                class="block"
                            >
                                <h3 class="inline text-base font-semibold text-gray-100 sm:text-lg">
                                    <span class="break-words group-hover:underline">{{ $addon->name }}</span>
                                    @if ($addon->latestVersion)
                                        <span class="text-nowrap font-light text-gray-400">
                                            {{ $addon->latestVersion->version }}
                                        </span>
                                    @endif
                                </h3>
                            </a>
                        </div>

                        {{-- Action menu --}}
                        @cachedCan('update', $addon)
                            <livewire:addon.action
                                wire:key="addon-action-card-{{ $addon->id }}"
                                :addon-id="$addon->id"
                                :addon-name="$addon->name"
                                :addon-disabled="(bool) $addon->disabled"
                                :addon-published="(bool) $addon->published_at && $addon->published_at <= now()"
                                :addon-detached="(bool) $addon->detached_at"
                            />
                        @endcachedCan
                    </div>

                    {{-- Info and versions row --}}
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        {{-- Left side: Created by and Downloads --}}
                        <div class="flex-1">
                            {{-- Created by info --}}
                            <p class="text-sm text-gray-400">
                                {{ __('Created by') }}
                                <span class="[&>span:not(:last-child)]:after:content-[',_']">
                                    @if ($addon->owner)
                                        <span><a
                                                href="{{ $addon->owner->profile_url }}"
                                                class="underline hover:text-gray-100"
                                            >{{ $addon->owner->name }}</a></span>
                                    @else
                                        Unknown
                                    @endif
                                    @if ($addon->additionalAuthors->isNotEmpty())
                                        @foreach ($addon->additionalAuthors as $author)
                                            <span><a
                                                    href="{{ $author->profile_url }}"
                                                    class="underline hover:text-gray-100"
                                                >{{ $author->name }}</a></span>
                                        @endforeach
                                    @endif
                                </span>
                            </p>

                            {{-- Download count --}}
                            <p
                                class="mt-1 text-sm text-gray-400"
                                title="{{ __('Exactly :downloads', ['downloads' => $addon->downloads]) }}"
                            >
                                {{ Number::downloads($addon->downloads) }}
                                {{ __(Str::plural('Download', $addon->downloads)) }}
                            </p>
                        </div>

                        {{-- Right side: Compatible Mod Versions --}}
                        @if ($compatibleModVersionsToShow()->isNotEmpty() || $hasNoCompatibleVersions())
                            <div class="sm:text-right">
                                @if ($compatibleModVersionsToShow()->isNotEmpty())
                                    <p class="mb-1 text-sm text-gray-400">
                                        {{ __('Compatible Mod Versions') }}:
                                    </p>
                                    <div class="flex flex-wrap gap-1 sm:justify-end">
                                        @foreach ($compatibleModVersionsToShow()->take(3) as $modVersion)
                                            @if ($modVersion->id === $latestModVersionId())
                                                <span
                                                    class="inline-flex items-center rounded bg-green-800 px-1.5 py-0.5 text-xs font-medium text-green-300"
                                                    title="{{ __('This is the latest mod version') }}"
                                                >
                                                    v{{ $modVersion->version }}
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center rounded bg-gray-800 px-1.5 py-0.5 text-xs font-medium text-gray-300"
                                                >
                                                    v{{ $modVersion->version }}
                                                </span>
                                            @endif
                                        @endforeach
                                        @if ($compatibleModVersionsToShow()->count() > 3)
                                            <flux:tooltip
                                                position="top"
                                                align="end"
                                                class="!inline-flex !items-center"
                                            >
                                                <span
                                                    class="inline-flex cursor-help items-center rounded bg-gray-800 px-1.5 py-0.5 text-xs font-medium text-gray-400"
                                                >
                                                    +{{ $compatibleModVersionsToShow()->count() - 3 }} more
                                                </span>
                                                <flux:tooltip.content class="max-w-xs text-left">
                                                    <div class="text-xs">
                                                        <div class="mb-1 text-left font-semibold">All Compatible Mod
                                                            Versions:</div>
                                                        <div class="flex flex-wrap justify-start gap-1">
                                                            @foreach ($compatibleModVersionsToShow() as $modVersion)
                                                                @if ($modVersion->id === $latestModVersionId())
                                                                    <span
                                                                        class="inline-flex items-center rounded bg-green-800 px-1 py-0.5 text-xs text-green-300"
                                                                        title="{{ __('This is the latest mod version') }}"
                                                                    >
                                                                        v{{ $modVersion->version }}
                                                                    </span>
                                                                @else
                                                                    <span
                                                                        class="inline-flex items-center rounded bg-gray-800 px-1 py-0.5 text-xs text-gray-300"
                                                                    >
                                                                        v{{ $modVersion->version }}
                                                                    </span>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </flux:tooltip.content>
                                            </flux:tooltip>
                                        @endif
                                    </div>
                                @elseif($hasNoCompatibleVersions())
                                    <span
                                        class="inline-flex items-center rounded bg-yellow-900 px-1.5 py-0.5 text-xs font-medium text-yellow-200"
                                    >
                                        {{ __('No compatible mod versions found') }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Addon Teaser/Description - Full width below image --}}
            @if ($addon->teaser)
                <div class="mt-4 border-t-2 border-gray-800 pt-3">
                    <p class="text-sm text-gray-200">
                        {{ $addon->teaser }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
