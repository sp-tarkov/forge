<div {{ $attributes->merge(['class' => 'addon-list-component relative']) }}>

    <livewire:ribbon.addon
        wire:key="addon-card-ribbon-{{ $addon->id }}"
        :addon-id="$addon->id"
        :disabled="$addon->disabled"
        :published-at="$addon->published_at?->toISOString()"
        :publicly-visible="$addon->isPubliclyVisible()"
    />

    <div
        class="bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl overflow-hidden group hover:shadow-lg hover:bg-gray-50 dark:hover:bg-black transition-all duration-200">
        {{-- Detached indicator if applicable --}}
        @if ($addon->isDetached() && auth()->user()?->isModOrAdmin())
            <div
                class="bg-yellow-50 dark:bg-yellow-900/20 border-b border-yellow-200 dark:border-yellow-800 px-3 sm:px-4 py-2">
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon.exclamation-circle class="size-4 text-yellow-600 dark:text-yellow-400" />
                    <span class="text-yellow-800 dark:text-yellow-300 font-medium">Detached Addon</span>
                </div>
            </div>
        @endif

        <div class="p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                {{-- Thumbnail - centered on mobile, left-aligned on desktop --}}
                @if ($addon->thumbnail)
                    <a
                        href="{{ route('addon.show', [$addon->id, $addon->slug]) }}"
                        wire:navigate
                        class="flex-shrink-0 block overflow-hidden rounded-lg mx-auto sm:mx-0"
                    >
                        <img
                            src="{{ $addon->thumbnailUrl }}"
                            alt="{{ $addon->name }}"
                            class="w-20 h-20 sm:w-16 sm:h-16 md:w-20 md:h-20 object-cover transform group-hover:scale-110 transition-transform duration-200"
                        >
                    </a>
                @else
                    <a
                        href="{{ route('addon.show', [$addon->id, $addon->slug]) }}"
                        wire:navigate
                        class="flex-shrink-0 block mx-auto sm:mx-0"
                    >
                        <div
                            class="w-20 h-20 sm:w-16 sm:h-16 md:w-20 md:h-20 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center">
                            <flux:icon.puzzle-piece
                                class="w-10 h-10 sm:w-8 sm:h-8 md:w-10 md:h-10 text-gray-400 dark:text-gray-600"
                            />
                        </div>
                    </a>
                @endif

                {{-- Content - centered text on mobile, left-aligned on desktop --}}
                <div class="flex-1 min-w-0">
                    {{-- Header with Title and Version --}}
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-2">
                        <div class="flex-1 min-w-0 text-center sm:text-left">
                            <a
                                href="{{ route('addon.show', [$addon->id, $addon->slug]) }}"
                                wire:navigate
                                class="block"
                            >
                                <h3 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-gray-100 inline">
                                    <span class="group-hover:underline break-words">{{ $addon->name }}</span>
                                    @if ($addon->latestVersion)
                                        <span class="font-light text-nowrap text-gray-600 dark:text-gray-400">
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
                    <div
                        class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 text-center sm:text-left">
                        {{-- Left side: Created by and Downloads --}}
                        <div class="flex-1">
                            {{-- Created by info --}}
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ __('Created by') }}
                                <span class="[&>span:not(:last-child)]:after:content-[',_']">
                                    @if ($addon->owner)
                                        <span><a
                                                href="{{ $addon->owner->profile_url }}"
                                                class="hover:text-gray-900 dark:hover:text-gray-100 underline"
                                            >{{ $addon->owner->name }}</a></span>
                                    @else
                                        Unknown
                                    @endif
                                    @if ($addon->authors->isNotEmpty())
                                        @foreach ($addon->authors as $author)
                                            <span><a
                                                    href="{{ $author->profile_url }}"
                                                    class="hover:text-gray-900 dark:hover:text-gray-100 underline"
                                                >{{ $author->name }}</a></span>
                                        @endforeach
                                    @endif
                                </span>
                            </p>

                            {{-- Download count --}}
                            <p
                                class="text-sm text-gray-600 dark:text-gray-400 mt-1"
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
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                        {{ __('Compatible Mod Versions') }}:
                                    </p>
                                    <div class="flex flex-wrap gap-1 justify-center sm:justify-end">
                                        @foreach ($compatibleModVersionsToShow()->take(3) as $modVersion)
                                            @if ($modVersion->id === $latestModVersionId())
                                                <span
                                                    class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-300"
                                                    title="{{ __('This is the latest mod version') }}"
                                                >
                                                    v{{ $modVersion->version }}
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300"
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
                                                    class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 cursor-help"
                                                >
                                                    +{{ $compatibleModVersionsToShow()->count() - 3 }} more
                                                </span>
                                                <flux:tooltip.content class="max-w-xs text-left">
                                                    <div class="text-xs">
                                                        <div class="font-semibold mb-1 text-left">All Compatible Mod
                                                            Versions:</div>
                                                        <div class="flex flex-wrap gap-1 justify-start">
                                                            @foreach ($compatibleModVersionsToShow() as $modVersion)
                                                                @if ($modVersion->id === $latestModVersionId())
                                                                    <span
                                                                        class="inline-flex items-center rounded px-1 py-0.5 text-xs bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-300"
                                                                        title="{{ __('This is the latest mod version') }}"
                                                                    >
                                                                        v{{ $modVersion->version }}
                                                                    </span>
                                                                @else
                                                                    <span
                                                                        class="inline-flex items-center rounded px-1 py-0.5 text-xs bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300"
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
                                        class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
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
                <div class="mt-4 pt-3 border-t-2 border-gray-300 dark:border-gray-800">
                    <p class="text-gray-900 dark:text-gray-200 text-sm text-center sm:text-left">
                        {{ $addon->teaser }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
