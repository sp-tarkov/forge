@props([
    'name',
    'modId',
    'latestVersionId',
    'downloadUrl',
    'versionString',
    'sptVersionFormatted' => null,
    'sptVersionColorClass' => null,
    'versionDescriptionHtml',
    'versionUpdatedAt',
    'fileSize' => null,
    'latestVersionDependencies' => null,
])

<div class="{{ $name === 'download-show-mobile' ? 'lg:hidden block' : 'hidden lg:block' }}">
    <flux:modal.trigger name="{{ $name }}">
        <button
            class="text-lg font-extrabold hover:bg-cyan-400 dark:hover:bg-cyan-600 shadow-md dark:shadow-gray-950 drop-shadow-2xl bg-cyan-500 dark:bg-cyan-700 rounded-xl w-full h-20"
        >
            <div class="flex flex-col justify-center items-center">
                <div>{{ __('Download Latest Version') }} ({{ $versionString }})</div>
                @if ($fileSize)
                    <div class="text-sm font-normal opacity-75">{{ $fileSize }}</div>
                @endif
            </div>
        </button>
    </flux:modal.trigger>

    {{-- Dependencies Display --}}
    @if ($latestVersionDependencies && $latestVersionDependencies->isNotEmpty())
        <div class="mt-6">
            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-3">{{ __('Required Mods:') }}</h3>
            <div class="space-y-3">
                @foreach ($latestVersionDependencies as $dependencyVersion)
                    @if ($dependencyVersion->mod)
                        <a
                            href="{{ $dependencyVersion->mod->detail_url }}"
                            class="block p-4 bg-gray-900 dark:bg-gray-900 rounded-lg hover:bg-gray-800 dark:hover:bg-gray-800 transition-colors shadow-md"
                        >
                            <div class="flex items-start gap-3">
                                @if ($dependencyVersion->mod->thumbnail)
                                    <img
                                        src="{{ $dependencyVersion->mod->thumbnailUrl }}"
                                        alt="{{ $dependencyVersion->mod->name }}"
                                        class="w-16 h-16 rounded flex-shrink-0"
                                    >
                                @else
                                    <div
                                        class="w-16 h-16 rounded flex-shrink-0 bg-gray-700 dark:bg-gray-700 flex items-center justify-center text-gray-400 dark:text-gray-400 text-sm font-bold"
                                    >
                                        {{ substr($dependencyVersion->mod->name, 0, 2) }}
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-base text-white dark:text-white">
                                        {{ $dependencyVersion->mod->name }}
                                    </div>
                                    <div class="text-sm text-gray-400 dark:text-gray-400 mt-1">
                                        {{ __('Created by') }}
                                        @if ($dependencyVersion->mod->owner)
                                            <x-user-name :user="$dependencyVersion->mod->owner" />
                                        @else
                                            {{ __('Unknown') }}
                                        @endif
                                    </div>
                                    @if ($dependencyVersion->mod->latestVersion)
                                        <div class="mt-2 flex items-center gap-2">
                                            @if ($dependencyVersion->mod->latestVersion->latestSptVersion)
                                                <span
                                                    class="badge-version {{ $dependencyVersion->mod->latestVersion->latestSptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap"
                                                >
                                                    {{ $dependencyVersion->mod->latestVersion->latestSptVersion->version_formatted }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($dependencyVersion->mod->teaser)
                                        <div class="text-xs text-gray-500 dark:text-gray-500 mt-2 line-clamp-2">
                                            {{ $dependencyVersion->mod->teaser }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-shrink-0 text-right">
                                    <div class="text-sm text-gray-400 dark:text-gray-400 mb-1">
                                        {{ Number::downloads($dependencyVersion->mod->downloads) }}
                                    </div>
                                    <svg
                                        class="w-5 h-5 text-gray-400 dark:text-gray-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 5l7 7-7 7"
                                        ></path>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- Download Dialog Modal --}}
    <flux:modal
        name="{{ $name }}"
        class="md:w-[600px] lg:w-[700px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Latest Version') }} {{ $versionString }}
                        </flux:heading>

                        <div class="flex items-center gap-3 mt-3 flex-wrap">
                            @if ($sptVersionFormatted)
                                <span
                                    class="badge-version {{ $sptVersionColorClass }} inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold text-nowrap shadow-sm"
                                >
                                    {{ $sptVersionFormatted }}
                                </span>
                            @endif

                            <flux:text class="text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Updated') }} {{ $versionUpdatedAt->dynamicFormat() }}
                            </flux:text>

                            @if ($fileSize)
                                <flux:text class="text-gray-600 dark:text-gray-400 text-sm">
                                    {{ $fileSize }}
                                </flux:text>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <flux:icon
                        name="document-text"
                        class="w-5 h-5 text-gray-500 dark:text-gray-400"
                    />
                    <flux:heading
                        size="md"
                        class="text-gray-900 dark:text-gray-100"
                    >
                        {{ __('Version Notes') }}
                    </flux:heading>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                    <div
                        class="p-6 prose prose-sm dark:prose-invert max-w-none overflow-y-auto max-h-80 text-gray-700 dark:text-gray-300">
                        {{--
                            !DANGER ZONE!

                            This field is cleaned by the backend, so we can trust it. Other fields are not. Only write out
                            fields like this when you're absolutely sure that the data is safe. Which is almost never.
                        --}}
                        {!! $versionDescriptionHtml !!}
                    </div>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center text-xs text-amber-600 dark:text-amber-400 max-w-sm">
                    <flux:icon
                        name="exclamation-triangle"
                        class="w-4 h-4 mr-2 flex-shrink-0"
                    />
                    <span class="leading-tight">
                        {{ __('This download is externally hosted.') }}<br />
                        {{ __('Always scan for viruses.') }}
                    </span>
                </div>

                <div class="flex gap-3">
                    <flux:button
                        variant="primary"
                        size="sm"
                        x-on:click="$flux.modal('{{ $name }}').close(); window.open('{{ $downloadUrl }}', '_blank')"
                        icon="arrow-down"
                    >
                        {{ __('Download') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
