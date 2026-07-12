<div
    {{ $attributes->merge(['class' => 'relative p-4 mb-4 sm:p-6 bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl filter-none group hover:shadow-lg hover:bg-black']) }}>

    <livewire:ribbon.mod-version
        wire:key="mod-version-show-ribbon-{{ $version->id }}"
        :version-id="$version->id"
        :disabled="$version->disabled"
        :published-at="$version->published_at?->toISOString()"
    />

    <div class="border-b-2 border-gray-800 pb-6">
        @cachedCan('update', $version)
            <livewire:mod.version-action
                wire:key="mod-version-show-action-{{ $version->id }}"
                :version-id="$version->id"
                :mod-id="$version->mod_id"
                :version-number="$version->version"
                :version-disabled="(bool) $version->disabled"
                :version-published="(bool) $version->published_at && $version->published_at <= now()"
            />
        @endcachedCan

        <div class="flex flex-col items-start sm:flex-row sm:justify-between">
            <div class="flex flex-col">
                <div class="flex items-center gap-2">
                    <flux:modal.trigger name="{{ $modalName() }}">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center text-3xl font-extrabold text-gray-200 hover:text-white hover:underline"
                        >
                            <span>{{ __('Version') }} {{ $version->version }}</span>
                        </button>
                    </flux:modal.trigger>
                    @if ($isVerified())
                        <flux:modal.trigger name="{{ $verificationModalName() }}">
                            <button
                                type="button"
                                data-test="verification-shield"
                                class="cursor-pointer"
                            >
                                <flux:tooltip
                                    content="{{ __('View File Verification') }}"
                                    position="right"
                                >
                                    <flux:icon
                                        icon="shield-check"
                                        variant="solid"
                                        class="size-6 text-blue-400 transition hover:text-blue-300 hover:drop-shadow-[0_0_8px_rgba(96,165,250,0.9)]"
                                    />
                                </flux:tooltip>
                            </button>
                        </flux:modal.trigger>
                    @endif
                </div>
                <div class="mt-3 flex flex-row flex-wrap items-center justify-start gap-2.5">
                    @if ($version->sptVersions->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-1">
                            @if ($version->latestSptVersion)
                                <span
                                    class="badge-version {{ $version->latestSptVersion->color_class }} inline-flex items-center text-nowrap rounded px-1.5 py-0.5 text-xs font-medium"
                                >
                                    {{ $version->latestSptVersion->version_formatted }}
                                </span>
                            @endif
                            @if ($version->sptVersions->count() > 1)
                                <flux:tooltip
                                    position="top"
                                    align="start"
                                    class="!inline-flex !items-center"
                                >
                                    <span
                                        class="inline-flex cursor-help items-center rounded bg-gray-800 px-1.5 py-0.5 text-xs font-medium text-gray-400"
                                    >
                                        +{{ $version->sptVersions->count() - 1 }} more
                                    </span>
                                    <flux:tooltip.content class="max-w-xs text-left">
                                        <div class="text-xs">
                                            <div class="mb-1 text-left font-semibold">All Compatible SPT Versions:</div>
                                            <div class="flex flex-wrap justify-start gap-1">
                                                @foreach ($version->sptVersions as $sptVersion)
                                                    <span
                                                        class="badge-version {{ $sptVersion->color_class }} inline-flex items-center rounded px-1 py-0.5 text-xs"
                                                    >
                                                        {{ $sptVersion->version }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </flux:tooltip.content>
                                </flux:tooltip>
                            @endif
                        </div>
                    @elseif ($version->spt_version_constraint === '')
                        <span
                            class="badge-version gray inline-flex items-center text-nowrap rounded px-1.5 py-0.5 text-xs font-medium"
                        >
                            {{ __('Legacy SPT Version') }}
                        </span>
                    @else
                        {{-- Has constraint but no matching SPT versions (invalid) --}}
                        <span
                            class="badge-version inline-flex items-center text-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700"
                        >
                            {{ __('Unknown SPT Version') }}
                        </span>
                    @endif
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
                    @if ($version->mod->addons_enabled && ($version->compatible_addons_count ?? 0) > 0)
                        <span class="text-gray-700">|</span>
                        <a
                            href="{{ route('mod.show', [$version->mod->id, $version->mod->slug]) }}?versionFilter={{ $version->id }}#addons"
                            class="inline-flex items-center gap-1 text-sm text-gray-400 hover:text-gray-100 hover:underline"
                            wire:navigate
                        >
                            <flux:icon
                                icon="puzzle-piece"
                                variant="outline"
                                class="h-4 w-4 group-hover:hidden"
                            />
                            <flux:icon
                                icon="puzzle-piece"
                                variant="solid"
                                class="hidden h-4 w-4 text-green-500 group-hover:block"
                            />
                            <span>View Addons</span>
                        </a>
                    @endif
                </div>
            </div>
            <div @class([
                'flex flex-col items-start text-gray-400 sm:items-end mt-4 sm:mt-0',
                'sm:pr-10' => $showActions ?? Gate::check('update', $version),
            ])>
                <p class="text-nowrap text-left sm:text-right">{{ __('Released') }}
                    {{ $version->created_at->dynamicFormat() }}
                </p>
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
                <span class="inline-flex items-center gap-1 text-left sm:text-right">
                    <flux:icon
                        icon="{{ $version->fika_compatibility->icon() }}"
                        class="{{ $version->fika_compatibility->colorClass() }} size-4"
                    />
                    <span class="text-gray-100">
                        {{ $version->fika_compatibility->label() }}
                    </span>
                </span>
            </div>
        </div>

        {{-- Display latest resolved dependencies --}}
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

    <x-mod.version-download-modal
        :name="$modalName()"
        :download-url="$version->downloadUrl()"
        :version-string="$version->version"
        :spt-version-formatted="$version->latestSptVersion?->version_formatted ?? ($version->spt_version_constraint === '' ? __('Legacy') : null)"
        :spt-version-color-class="$version->latestSptVersion?->color_class ?? ($version->spt_version_constraint === '' ? 'gray' : null)"
        :version-description-html="$version->description_html"
        :version-updated-at="$version->updated_at"
        :file-size="$version->formatted_file_size"
        :dependencies="$version->latestDependenciesResolved"
        :is-latest="$isLatest()"
    />

    @if ($isVerified())
        <flux:modal
            name="{{ $verificationModalName() }}"
            class="md:w-[600px] lg:w-[700px]"
        >
            <livewire:verification-details
                wire:key="verification-details-{{ $version->id }}"
                :verifiable-id="$version->id"
                :verifiable-type="\App\Models\ModVersion::class"
            />
        </flux:modal>
    @endif
</div>
