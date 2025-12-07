@props([
    'mod',
    'version',
    'section' => 'default',
    'homepageFeatured' => false,
    'placeholderBg' => 'bg-gray-100 dark:bg-gray-800',
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
        class="flex flex-col group h-full w-full bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl overflow-hidden hover:shadow-lg hover:bg-gray-50 dark:hover:bg-black hover:shadow-gray-400 dark:hover:shadow-black transition-shadow ease-out duration-300"
    >
        <div class="h-auto md:h-full md:flex">
            <div class="relative h-auto md:h-full md:shrink-0 overflow-hidden">
                @if ($mod->thumbnail)
                    <img
                        src="{{ $mod->thumbnailUrl }}"
                        alt="{{ $mod->name }}"
                        class="h-48 w-full object-cover md:h-full md:w-48 transform group-hover:scale-110 transition-transform duration-200"
                    >
                @else
                    <div class="h-48 w-full md:h-full md:w-48 {{ $placeholderBg }} flex items-center justify-center">
                        <flux:icon.cube-transparent class="w-24 h-24 text-gray-400 dark:text-gray-600" />
                    </div>
                @endif
            </div>

            <div class="flex flex-col w-full justify-between p-5">
                <div class="pb-3">
                    <h3 class="my-1 text-lg leading-tight font-medium text-black dark:text-white group-hover:underline">
                        {{ $mod->name }}
                        <span class="font-light text-nowrap text-gray-600 dark:text-gray-400">
                            {{ $version->version }}
                        </span>
                    </h3>
                    <p class="no-underline mb-2 text-sm italic text-slate-600 dark:text-gray-200">
                        {{ __('Created by :owner', ['owner' => $mod->owner?->name ?? '']) }}
                    </p>
                    @if ($version?->latestSptVersion)
                        <p
                            class="badge-version {{ $version->latestSptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 mb-2 text-xs font-medium text-nowrap">
                            {{ $version->latestSptVersion->version_formatted }}
                        </p>
                    @endif
                    <p class="text-slate-500 dark:text-gray-300">
                        {{ Str::limit($mod->teaser) }}
                    </p>
                </div>

                <div class="text-slate-700 dark:text-gray-300 text-sm">
                    <div class="flex items-end w-full text-sm">
                        @if (($mod->updated_at || $mod->created_at) && $version)
                            <div class="flex items-end w-full">
                                <div class="flex items-center gap-1">
                                    <flux:icon.calendar class="size-5" />
                                    <x-time :datetime="$version->created_at" />
                                </div>
                            </div>
                        @endif
                        <div class="flex justify-end items-center gap-1">
                            <span
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
