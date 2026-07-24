<div
    {{ $attributes->merge(['class' => 'relative isolate p-4 mb-4 sm:p-6 bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl filter-none group hover:shadow-lg hover:bg-black']) }}>

    <livewire:ribbon.addon-version
        wire:key="addon-version-show-ribbon-{{ $version->id }}"
        :version-id="$version->id"
        :disabled="$version->disabled"
        :published-at="$version->published_at?->toISOString()"
    />

    <div class="border-b-2 border-gray-800 pb-6">
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
                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('addon.version.download', [$addon->id, $addon->slug, $version->version]) }}"
                        class="inline-flex items-center text-3xl font-extrabold text-gray-200 hover:text-white hover:underline"
                        rel="nofollow"
                    >
                        <span>{{ __('Version') }} {{ $version->version }}</span>
                        <flux:tooltip
                            content="Download Addon Version"
                            position="right"
                        >
                            <flux:icon
                                icon="arrow-down-on-square-stack"
                                class="ml-2 inline-block size-6"
                            />
                        </flux:tooltip>
                    </a>
                    <livewire:verification-status
                        wire:key="verification-status-addon-{{ $version->id }}"
                        :verifiable-id="$version->id"
                        :verifiable-type="\App\Models\AddonVersion::class"
                        :modal-name="$verificationModalName()"
                    />
                </div>
                <div class="mt-3 flex flex-row flex-wrap items-center justify-start gap-2.5">
                    <div class="flex items-center gap-2.5">
                        @if ($version->formatted_file_size)
                            <p class="text-sm text-gray-400">
                                {{ $version->formatted_file_size }}
                            </p>
                        @endif
                        <p
                            class="text-sm text-gray-300"
                            title="{{ __('Exactly') }} {{ $version->downloads }}"
                        >
                            {{ Number::downloads($version->downloads) }}
                            {{ __(Str::plural('Download', $version->downloads)) }}
                        </p>
                    </div>
                </div>
            </div>
            <div @class([
                'flex flex-col items-start text-gray-400 sm:items-end mt-4 sm:mt-0',
                'sm:pr-10' => $showActions ?? Gate::check('update', $version),
            ])>
                <p class="text-nowrap text-left sm:text-right">{{ __('Released') }}
                    {{ $version->published_at?->dynamicFormat() ?? $version->created_at->dynamicFormat() }}</p>
                @if ($version->virusTotalLinks->isNotEmpty())
                    <div
                        x-data="{ isMobile: window.innerWidth < 640 }"
                        x-init="window.addEventListener('resize', () => { isMobile = window.innerWidth < 640 })"
                        class="text-left sm:!flex sm:!justify-end sm:text-right"
                    >
                        <flux:tooltip
                            position="top"
                            align="start"
                            gap="0"
                            x-show="isMobile"
                        >
                            <span class="cursor-help text-gray-200 underline">
                                {{ __('VirusTotal Results') }}
                            </span>
                            <flux:tooltip.content class="max-w-xs text-left">
                                <div class="text-xs">
                                    <div class="mb-1 text-left font-semibold">{{ __('VirusTotal Results:') }}</div>
                                    <div class="space-y-1.5">
                                        @foreach ($version->virusTotalLinks as $virusTotalLink)
                                            <p class="truncate">
                                                @if ($virusTotalLink->label !== '')
                                                    <span class="text-gray-200">{{ $virusTotalLink->label }}:</span>
                                                    <a
                                                        href="{{ $virusTotalLink->url }}"
                                                        title="{{ $virusTotalLink->url }}"
                                                        target="_blank"
                                                        class="text-gray-200 underline hover:text-white"
                                                    >
                                                        {{ $virusTotalLink->url }}
                                                    </a>
                                                @else
                                                    <a
                                                        href="{{ $virusTotalLink->url }}"
                                                        title="{{ $virusTotalLink->url }}"
                                                        target="_blank"
                                                        class="text-gray-200 underline hover:text-white"
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
                            <span class="cursor-help text-gray-200 underline">
                                {{ __('VirusTotal Results') }}
                            </span>
                            <flux:tooltip.content class="max-w-xs text-left">
                                <div class="text-xs">
                                    <div class="mb-1 text-left font-semibold">{{ __('VirusTotal Results:') }}</div>
                                    <div class="space-y-1.5">
                                        @foreach ($version->virusTotalLinks as $virusTotalLink)
                                            <p class="truncate">
                                                @if ($virusTotalLink->label !== '')
                                                    <span class="text-gray-200">{{ $virusTotalLink->label }}:</span>
                                                    <a
                                                        href="{{ $virusTotalLink->url }}"
                                                        title="{{ $virusTotalLink->url }}"
                                                        target="_blank"
                                                        class="text-gray-200 underline hover:text-white"
                                                    >
                                                        {{ $virusTotalLink->url }}
                                                    </a>
                                                @else
                                                    <a
                                                        href="{{ $virusTotalLink->url }}"
                                                        title="{{ $virusTotalLink->url }}"
                                                        target="_blank"
                                                        class="text-gray-200 underline hover:text-white"
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
                <p class="mb-2 text-sm font-semibold text-gray-100">
                    {{ __('Compatible with mod versions:') }}
                </p>
                <div class="flex flex-wrap gap-1">
                    @foreach ($version->getSortedCompatibleModVersions() as $modVersion)
                        @if ($modVersion->id === ($addon->mod->latestVersion->id ?? null))
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
                </div>
            </div>
        @endif

        {{-- Display mod dependencies --}}
        @if ($version->latestDependenciesResolved->isNotEmpty())
            <p class="mt-3 text-gray-400">
                {{ __('Dependencies:') }}
            </p>
            <ul>
                @foreach ($version->latestDependenciesResolved as $resolvedDependency)
                    @continue($resolvedDependency->mod === null)
                    <li>
                        <a
                            href="{{ $resolvedDependency->mod->detail_url }}"
                            class="text-gray-200 hover:text-white hover:underline"
                        >
                            {{ $resolvedDependency->mod->name }}&nbsp;({{ $resolvedDependency->version }})
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <div class="user-markdown pt-3 text-gray-400">
        {{--
        !DANGER ZONE!

        This field is cleaned by the backend, so we can trust it. Other fields are not. Only write out
        fields like this when you're absolutely sure that the data is safe. Which is almost never.
        --}}
        {!! $version->description_html !!}
    </div>

    <flux:modal
        name="{{ $verificationModalName() }}"
        variant="flyout"
        position="left"
        class="md:w-[600px] lg:w-[700px]"
    >
        <livewire:verification-details
            wire:key="verification-details-addon-{{ $version->id }}"
            :verifiable-id="$version->id"
            :verifiable-type="\App\Models\AddonVersion::class"
        />
    </flux:modal>
</div>
