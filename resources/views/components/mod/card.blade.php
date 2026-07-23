@props([
    'mod',
    'version',
    'section' => 'default',
    'homepageFeatured' => false,
    'placeholderBg' => 'bg-gray-800',
    'showActions' => null,
    'eager' => false,
    'favouritesCount' => null,
])

<div {{ $attributes->merge(['class' => 'mod-list-component relative isolate mx-auto max-w-2xl h-full w-full']) }}>

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
        class="@container group flex h-full w-full flex-col overflow-hidden rounded-xl bg-gray-950 shadow-md shadow-gray-950 drop-shadow-2xl hover:bg-black"
    >
        <div class="@lg:flex-1 flex flex-row">
            <div class="@lg:m-4 relative m-3 mr-0 shrink-0 rounded-lg">
                {{-- Default stripe background --}}
                <div
                    class="absolute inset-0 rounded-lg bg-[repeating-linear-gradient(45deg,#020509,#020509_4px,#030712_4px,#030712_8px)] transition-opacity duration-200 group-hover:opacity-0">
                </div>
                {{-- Hover stripe background --}}
                <div
                    class="absolute inset-0 rounded-lg bg-[repeating-linear-gradient(45deg,#000000,#000000_4px,#010203_4px,#010203_8px)] opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                </div>
                {{-- Thumbnail content --}}
                <div class="relative overflow-hidden rounded-t-lg">
                    @if ($mod->thumbnail)
                        <img
                            src="{{ $mod->thumbnailUrl }}"
                            @if ($mod->thumbnailSrcset) srcset="{{ $mod->thumbnailSrcset }}"
                                sizes="(min-width: 1024px) 12rem, 8rem" @endif
                            alt="{{ $mod->name }}"
                            width="192"
                            height="192"
                            loading="{{ $eager ? 'eager' : 'lazy' }}"
                            decoding="async"
                            class="@lg:size-48 size-32 transform object-cover transition-transform duration-200 group-hover:scale-105"
                        >
                    @else
                        <div class="@lg:size-48 flex size-32 items-center justify-center">
                            <flux:icon.cube-transparent class="@lg:size-24 size-16 text-gray-600" />
                        </div>
                    @endif
                </div>
            </div>

            <div class="@lg:p-4 @lg:pl-0 @lg:justify-between flex w-full flex-col p-3">
                <div class="@lg:pb-3">
                    <h3 @class([
                        'text-lg leading-tight font-medium text-white',
                        'pr-10 lg:pr-12' => $showActions ?? Gate::check('update', $mod),
                    ])>
                        <span class="group-hover:underline">{{ $mod->name }}</span>
                        @if ($version)
                            <span class="text-nowrap font-light text-gray-400">
                                {{ $version->version }}
                            </span>
                        @endif
                    </h3>
                    <p class="@lg:mt-0 @lg:mb-2 mt-0.5 text-sm italic text-gray-200 no-underline">
                        {{ __('Created by :owner', ['owner' => $mod->owner?->name ?? '']) }}
                    </p>
                    @if ($version?->latestSptVersion)
                        <p
                            class="badge-version {{ $version->latestSptVersion->color_class }} @lg:mt-0 @lg:mb-2 mt-1.5 inline-flex items-center text-nowrap rounded-md px-2 py-1 text-xs font-medium">
                            {{ $version->latestSptVersion->version_formatted }}
                        </p>
                    @elseif ($version && $version->spt_version_constraint === '')
                        <p
                            class="badge-version gray @lg:mt-0 @lg:mb-2 mt-1.5 inline-flex items-center text-nowrap rounded-md px-2 py-1 text-xs font-medium">
                            {{ __('Legacy SPT Version') }}
                        </p>
                    @endif
                    {{-- Description: hidden at small, shown at @lg --}}
                    <p class="@lg:block hidden text-gray-300">
                        {{ Str::limit($mod->teaser) }}
                    </p>
                </div>

                {{-- Date/downloads --}}
                <div class="@lg:mt-0 mt-2 text-sm text-gray-300">
                    <div class="flex w-full items-center text-sm">
                        @if ($favouritesCount !== null)
                            <div class="flex w-full items-center">
                                <div
                                    class="flex items-center gap-1"
                                    title="{{ Number::format($favouritesCount) }} {{ __(Str::plural('Favourite', $favouritesCount)) }}"
                                >
                                    <flux:icon.heart class="size-5 group-hover:hidden" />
                                    <flux:icon.heart
                                        variant="solid"
                                        class="hidden size-5 text-rose-500 group-hover:block"
                                    />
                                    <span class="pt-0.5">{{ Number::format($favouritesCount) }}</span>
                                </div>
                            </div>
                        @elseif (($mod->updated_at || $mod->created_at) && $version)
                            <div class="flex w-full items-center">
                                <div class="flex items-center gap-1">
                                    <flux:icon.calendar class="size-5" />
                                    <span class="pt-0.5"><x-time :datetime="$version->created_at" /></span>
                                </div>
                            </div>
                        @endif
                        <div class="flex items-center justify-end gap-1">
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
            <p class="text-gray-300">
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
