@props([
    'mod',
    'version',
    'section' => 'default',
    'homepageFeatured' => false,
    'placeholderBg' => 'bg-gray-100 dark:bg-gray-800',
    'showActions' => null,
])

<div {{ $attributes->merge(['class' => 'mod-list-component relative mx-auto max-w-2xl h-full w-full']) }}>

    <livewire:ribbon.mod
        wire:key="mod-card-ribbon-{{ $section }}-{{ $mod->id }}"
        :mod-id="$mod->id"
        :disabled="$mod->disabled"
        :published-at="$mod->published_at?->toISOString()"
        :featured="$mod->featured"
        :homepage-featured="$homepageFeatured"
        :publicly-visible="$mod->isPubliclyVisible()"
    />

    <a
        href="{{ $mod->detail_url }}"
        wire:navigate
        class="@container flex flex-col group h-full w-full bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl overflow-hidden hover:bg-gray-50 dark:hover:bg-black"
    >
        <div class="flex flex-row @lg:flex-1">
            <div class="relative shrink-0 m-3 @lg:m-4 mr-0 rounded-lg">
                {{-- Default stripe background --}}
                <div
                    class="absolute inset-0 rounded-lg bg-[repeating-linear-gradient(45deg,#f9fafb,#f9fafb_4px,#ffffff_4px,#ffffff_8px)] dark:bg-[repeating-linear-gradient(45deg,#020509,#020509_4px,#030712_4px,#030712_8px)] transition-opacity duration-200 group-hover:opacity-0">
                </div>
                {{-- Hover stripe background --}}
                <div
                    class="absolute inset-0 rounded-lg bg-[repeating-linear-gradient(45deg,#f0f1f3,#f0f1f3_4px,#f9fafb_4px,#f9fafb_8px)] dark:bg-[repeating-linear-gradient(45deg,#000000,#000000_4px,#010203_4px,#010203_8px)] opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                </div>
                {{-- Thumbnail content --}}
                <div class="relative overflow-hidden rounded-t-lg">
                    @if ($mod->thumbnail)
                        <img
                            src="{{ $mod->thumbnailUrl }}"
                            alt="{{ $mod->name }}"
                            class="size-32 @lg:size-48 object-cover transform group-hover:scale-105 transition-transform duration-200"
                        >
                    @else
                        <div class="size-32 @lg:size-48 flex items-center justify-center">
                            <flux:icon.cube-transparent class="size-16 @lg:size-24 text-gray-400 dark:text-gray-600" />
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex flex-col w-full p-3 @lg:p-4 @lg:pl-0 @lg:justify-between">
                <div class="@lg:pb-3">
                    <h3 @class([
                        'text-lg leading-tight font-medium text-black dark:text-white',
                        'pr-10 lg:pr-12' => $showActions ?? Gate::check('update', $mod),
                    ])>
                        <span class="group-hover:underline">{{ $mod->name }}</span>
                        @if ($version)
                            <span class="font-light text-nowrap text-gray-600 dark:text-gray-400">
                                {{ $version->version }}
                            </span>
                        @endif
                    </h3>
                    <p class="no-underline text-sm italic text-slate-600 dark:text-gray-200 mt-0.5 @lg:mt-0 @lg:mb-2">
                        {{ __('Created by :owner', ['owner' => $mod->owner?->name ?? '']) }}
                    </p>
                    @if ($version?->latestSptVersion)
                        <p
                            class="badge-version {{ $version->latestSptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 mt-1.5 @lg:mt-0 @lg:mb-2 text-xs font-medium text-nowrap">
                            {{ $version->latestSptVersion->version_formatted }}
                        </p>
                    @elseif ($version && $version->spt_version_constraint === '')
                        <p
                            class="badge-version gray inline-flex items-center rounded-md px-2 py-1 mt-1.5 @lg:mt-0 @lg:mb-2 text-xs font-medium text-nowrap">
                            {{ __('Legacy SPT Version') }}
                        </p>
                    @endif
                    {{-- Description: hidden at small, shown at @lg --}}
                    <p class="hidden @lg:block text-slate-500 dark:text-gray-300">
                        {{ Str::limit($mod->teaser) }}
                    </p>
                </div>

                {{-- Date/downloads --}}
                <div class="text-slate-700 dark:text-gray-300 text-sm mt-2 @lg:mt-0">
                    <div class="flex items-center w-full text-sm">
                        @if (($mod->updated_at || $mod->created_at) && $version)
                            <div class="flex items-center w-full">
                                <div class="flex items-center gap-1">
                                    <flux:icon.calendar class="size-5" />
                                    <span class="pt-0.5"><x-time :datetime="$version->created_at" /></span>
                                </div>
                            </div>
                        @endif
                        <div class="flex justify-end items-center gap-1">
                            <span
                                class="pt-0.5"
                                title="{{ Number::format($mod->downloads) }} {{ __(Str::plural('Download', $mod->downloads)) }}"
                            >
                                {{ Number::downloads($mod->downloads) }}
                            </span>
                            <flux:icon.arrow-down-tray class="size-5" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Description: shown at small breakpoint only, full width below thumbnail row --}}
        <div class="@lg:hidden px-3 pb-3">
            <p class="text-slate-500 dark:text-gray-300">
                {{ Str::limit($mod->teaser) }}
            </p>
        </div>
    </a>

    @cachedCan('update', $mod)
        <livewire:mod.action
            wire:key="mod-action-{{ $section }}-{{ $mod->id }}"
            :mod-id="$mod->id"
            :mod-name="$mod->name"
            :mod-featured="(bool) $mod->featured"
            :mod-disabled="(bool) $mod->disabled"
            :mod-published="(bool) $mod->published_at && $mod->published_at <= now()"
            :homepage-featured="$homepageFeatured"
        />
    @endcachedCan
</div>
