@props(['version', 'addon'])

<div
    {{ $attributes->merge(['class' => 'relative p-4 mb-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none group hover:shadow-lg hover:bg-gray-50 dark:hover:bg-black']) }}>

    <livewire:ribbon.addon-version
        wire:key="addon-version-show-ribbon-{{ $version->id }}"
        :version-id="$version->id"
        :disabled="$version->disabled"
        :published-at="$version->published_at?->toISOString()"
    />

    <div class="pb-6 border-b-2 border-gray-200 dark:border-gray-800">
        @cachedCan('update', $version)
            <livewire:addon.version-action
                wire:key="addon-version-show-action-{{ $version->id }}"
                :version-id="$version->id"
                :addon-id="$version->addon_id"
                :version-number="$version->version"
                :version-disabled="(bool) $version->disabled"
                :version-published="(bool) $version->published_at && $version->published_at <= now()"
            />
        @endcachedCan

        <div class="flex flex-col items-start sm:flex-row sm:justify-between">
            <div class="flex flex-col">
                <a
                    href="{{ route('addon.version.download', [$addon->id, $addon->slug, $version->version]) }}"
                    class="inline-flex items-center text-3xl font-extrabold text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white hover:underline"
                    rel="nofollow"
                >
                    <span>{{ __('Version') }} {{ $version->version }}</span>
                    <flux:tooltip
                        content="Download Addon Version"
                        position="right"
                    >
                        <flux:icon
                            icon="arrow-down-on-square-stack"
                            class="inline-block size-6 ml-2"
                        />
                    </flux:tooltip>
                </a>
                <div class="mt-3 flex flex-row justify-start items-center gap-2.5">
                    @if ($version->formatted_file_size)
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $version->formatted_file_size }}
                        </p>
                    @endif
                    <p
                        class="text-sm text-gray-800 dark:text-gray-300"
                        title="{{ __('Exactly') }} {{ $version->downloads }}"
                    >
                        {{ Number::downloads($version->downloads) }}
                        {{ __(Str::plural('Download', $version->downloads)) }}
                    </p>
                </div>
            </div>
            <div class="flex flex-col items-start text-gray-700 dark:text-gray-400 sm:items-end mt-4 sm:mt-0">
                <p class="text-left sm:text-right">{{ __('Released') }}
                    {{ $version->published_at?->dynamicFormat() ?? $version->created_at->dynamicFormat() }}</p>
                @if ($version->virusTotalLinks->isNotEmpty())
                    <div
                        x-data="{ isMobile: window.innerWidth < 640 }"
                        x-init="window.addEventListener('resize', () => { isMobile = window.innerWidth < 640 })"
                        class="text-left sm:text-right sm:!flex sm:!justify-end"
                    >
                        <flux:tooltip
                            position="top"
                            align="start"
                            gap="0"
                            x-show="isMobile"
                        >
                            <span class="underline text-gray-800 dark:text-gray-200 cursor-help">
                                {{ __('VirusTotal Results') }}
                            </span>
                            <flux:tooltip.content class="max-w-xs text-left">
                                <div class="text-xs">
                                    <div class="font-semibold mb-1 text-left">{{ __('VirusTotal Results:') }}</div>
                                    <div class="space-y-1.5">
                                        @foreach ($version->virusTotalLinks as $virusTotalLink)
                                            <p class="truncate">
                                                @if ($virusTotalLink->label !== '')
                                                    <span
                                                        class="text-gray-800 dark:text-gray-200">{{ $virusTotalLink->label }}:</span>
                                                    <a
                                                        href="{{ $virusTotalLink->url }}"
                                                        title="{{ $virusTotalLink->url }}"
                                                        target="_blank"
                                                        class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                                    >
                                                        {{ $virusTotalLink->url }}
                                                    </a>
                                                @else
                                                    <a
                                                        href="{{ $virusTotalLink->url }}"
                                                        title="{{ $virusTotalLink->url }}"
                                                        target="_blank"
                                                        class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                                    >
                                                        {{ $virusTotalLink->url }}
                                                    </a>
                                                @endif
                                            </p>
                                        @endforeach
                                    </div>
                                </div>
                            </flux:tooltip.content>
                        </flux:tooltip>
                        <flux:tooltip
                            position="top"
                            align="end"
                            gap="0"
                            x-show="!isMobile"
                        >
                            <span class="underline text-gray-800 dark:text-gray-200 cursor-help">
                                {{ __('VirusTotal Results') }}
                            </span>
                            <flux:tooltip.content class="max-w-xs text-left">
                                <div class="text-xs">
                                    <div class="font-semibold mb-1 text-left">{{ __('VirusTotal Results:') }}</div>
                                    <div class="space-y-1.5">
                                        @foreach ($version->virusTotalLinks as $virusTotalLink)
                                            <p class="truncate">
                                                @if ($virusTotalLink->label !== '')
                                                    <span
                                                        class="text-gray-800 dark:text-gray-200">{{ $virusTotalLink->label }}:</span>
                                                    <a
                                                        href="{{ $virusTotalLink->url }}"
                                                        title="{{ $virusTotalLink->url }}"
                                                        target="_blank"
                                                        class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                                    >
                                                        {{ $virusTotalLink->url }}
                                                    </a>
                                                @else
                                                    <a
                                                        href="{{ $virusTotalLink->url }}"
                                                        title="{{ $virusTotalLink->url }}"
                                                        target="_blank"
                                                        class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                                    >
                                                        {{ $virusTotalLink->url }}
                                                    </a>
                                                @endif
                                            </p>
                                        @endforeach
                                    </div>
                                </div>
                            </flux:tooltip.content>
                        </flux:tooltip>
                    </div>
                @endif
            </div>
        </div>

        {{-- Display compatible mod versions --}}
        @if ($version->compatibleModVersions->isNotEmpty())
            <div class="mt-4">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    {{ __('Compatible with mod versions:') }}
                </p>
                <div class="flex flex-wrap gap-1">
                    @foreach ($version->getSortedCompatibleModVersions() as $modVersion)
                        @if ($modVersion->id === ($addon->mod->latestVersion->id ?? null))
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
                </div>
            </div>
        @endif

        {{-- Display mod dependencies --}}
        @if ($version->latestDependenciesResolved->isNotEmpty())
            <p class="mt-3 text-gray-700 dark:text-gray-400">
                {{ __('Dependencies:') }}
            </p>
            <ul>
                @foreach ($version->latestDependenciesResolved as $resolvedDependency)
                    <li>
                        <a
                            href="{{ $resolvedDependency->mod->detail_url }}"
                            class="hover:underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                        >
                            {{ $resolvedDependency->mod->name }}&nbsp;({{ $resolvedDependency->version }})
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <div class="pt-3 user-markdown text-gray-700 dark:text-gray-400">
        {{--
        !DANGER ZONE!

        This field is cleaned by the backend, so we can trust it. Other fields are not. Only write out
        fields like this when you're absolutely sure that the data is safe. Which is almost never.
        --}}
        {!! $version->description_html !!}
    </div>
</div>
