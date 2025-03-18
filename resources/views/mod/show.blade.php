<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
            {{ __('Mod Details') }}
        </h2>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 max-w-7xl mx-auto py-6 px-4 gap-6 sm:px-6 lg:px-8">
        <div class="lg:col-span-2 flex flex-col gap-6">

            {{-- Main Mod Details Card --}}
            <div class="relative p-4 sm:p-6 text-center sm:text-left bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                @if (auth()->user()?->isModOrAdmin())
                    <livewire:mod.moderation :mod="$mod" :current-route="request()->route()->getName() ?? ''" />
                @endif

                <livewire:mod.ribbon key="mod-ribbon-{{ $mod->id }}" :id="$mod->id" :disabled="$mod->disabled" :featured="$mod->featured" />

                <div class="flex flex-col sm:flex-row gap-4 sm:gap-6">
                    <div class="grow-0 shrink-0 flex justify-center items-center">
                        @if ($mod->thumbnail)
                            <img src="{{ $mod->thumbnailUrl }}" alt="{{ $mod->name }}" class="w-36 rounded-lg">
                        @else
                            <img src="https://placehold.co/144x144/31343C/EEE?font=source-sans-pro&text={{ urlencode($mod->name) }}" alt="{{ $mod->name }}" class="w-36 rounded-lg">
                        @endif
                    </div>
                    <div class="grow flex flex-col justify-center items-center sm:items-start text-gray-900 dark:text-gray-200">
                        <div class="flex justify-between items-center space-x-3">
                            <h2 class="pb-1 sm:pb-2 text-3xl font-bold text-gray-900 dark:text-white">
                                {{ $mod->name }}
                                @if ($mod->latestVersion)
                                    <span class="font-light text-nowrap text-gray-600 dark:text-gray-400">
                                        {{ $mod->latestVersion->version }}
                                    </span>
                                @endif
                            </h2>
                        </div>
                        <p>
                            {{ __('Created by') }}
                            @foreach ($mod->users as $user)
                                <a href="{{ $user->profile_url }}" class="text-slate-800 dark:text-gray-200 hover:underline">{{ $user->name }}</a>{{ $loop->last ? '' : ',' }}
                            @endforeach
                        </p>
                        <p title="{{ __('Exactly') }} {{ $mod->downloads }}">{{ Number::downloads($mod->downloads) }} {{ __('Downloads') }}</p>
                        @if ($mod->latestVersion?->latestSptVersion)
                            <p class="mt-2">
                                <span class="badge-version {{ $mod->latestVersion->latestSptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
                                    {{ $mod->latestVersion->latestSptVersion->version_formatted }} {{ __('Compatible') }}
                                </span>
                            </p>
                        @else
                            <p class="mt-2">
                                <span class="badge-version bg-gray-200 text-gray-700 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
                                    {{ __('Unknown SPT Version') }}
                                </span>
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Mod Teaser --}}
                @if ($mod->teaser)
                    <p class="mt-6 pt-3 border-t-2 border-gray-300 dark:border-gray-800 text-gray-900 dark:text-gray-200">{{ $mod->teaser }}</p>
                @endif
            </div>

            {{-- Mobile Download Button --}}
            @if ($mod->latestVersion)
                <livewire:mod.download-button :mod="$mod" classes="block lg:hidden"/>
            @endif

            {{-- Tabs --}}
            <div x-data="{ selectedTab: window.location.hash ? window.location.hash.substring(1) : 'description' }"
                 x-init="$watch('selectedTab', (tab) => {window.location.hash = tab})"
                 class="lg:col-span-2 flex flex-col gap-6">
                <div>
                    {{-- Mobile Dropdown --}}
                    <div class="sm:hidden">
                        <label for="tabs" class="sr-only">{{ __('Select a tab') }}</label>
                        <select id="tabs" name="tabs" x-model="selectedTab" class="block w-full rounded-md dark:text-white bg-gray-100 dark:bg-gray-950 border-gray-300 dark:border-gray-700 focus:border-cyan-500 dark:focus:border-cyan-400 focus:ring-cyan-500 dark:focus:ring-cyan-400">
                            <option value="description">{{ __('Description') }}</option>
                            <option value="versions">{{ __('Versions') }}</option>
                            <option value="comments">{{ __('Comments') }}</option>
                        </select>
                    </div>

                    {{-- Desktop Tabs --}}
                    <div class="hidden sm:block">
                        <nav class="isolate flex divide-x divide-gray-300 dark:divide-gray-800 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl" aria-label="Tabs">
                            <x-tab-button name="Description" />
                            <x-tab-button name="Versions" />
                            <x-tab-button name="Comments" />
                        </nav>
                    </div>
                </div>

                {{-- Mod Description --}}
                <div x-show="selectedTab === 'description'" class="user-markdown p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                    {{-- The description below is safe to write directly because it has been run though HTMLPurifier. --}}
                    {!! Str::markdown($mod->description) !!}
                </div>

                {{-- Mod Versions --}}
                <div x-show="selectedTab === 'versions'">
                    @foreach ($mod->versions as $version)
                        <div class="p-4 mb-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">

                            <livewire:mod.ribbon key="mod-version-ribbon-{{ $version->id }}" :id="$version->id" :disabled="$version->disabled" />

                            <div class="pb-6 border-b-2 border-gray-200 dark:border-gray-800">
                                @if (auth()->user()?->isModOrAdmin())
                                    {{-- TODO: <livewire:modVersion.moderation :modVersion="$version" /> --}}
                                @endif
                                <div class="flex items-center justify-between">
                                    <a class="text-2xl font-extrabold text-gray-900 dark:text-white" href="{{ $version->downloadUrl() }}">
                                        {{ __('Version') }} {{ $version->version }}
                                    </a>
                                    <p class="text-gray-800 dark:text-gray-300"
                                       title="{{ __('Exactly') }} {{ $version->downloads }}">{{ Number::downloads($version->downloads) }} {{ __('Downloads') }}</p>
                                </div>
                                <div class="flex items-center justify-between">
                                    @if ($version->latestSptVersion)
                                        <span class="badge-version {{ $version->latestSptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
                                            {{ $version->latestSptVersion->version_formatted }}
                                        </span>
                                    @else
                                        <span class="badge-version bg-gray-100 text-gray-700 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
                                            {{ __('Unknown SPT Version') }}
                                        </span>
                                    @endif
                                    <a href="{{ $version->virus_total_link }}" class="text-cyan-600 dark:text-cyan-400 hover:underline">{{__('Virus Total Results')}}</a>
                                </div>
                                <div class="flex items-center justify-between text-gray-700 dark:text-gray-400">
                                    <span>{{ __('Created') }} {{ Carbon::dynamicFormat($version->created_at) }}</span>
                                    <span>{{ __('Updated') }} {{ Carbon::dynamicFormat($version->updated_at) }}</span>
                                </div>

                                {{-- Display latest resolved dependencies --}}
                                @if ($version->latestResolvedDependencies->isNotEmpty())
                                    <div class="text-gray-700 dark:text-gray-400">
                                        {{ __('Dependencies:') }}
                                        @foreach ($version->latestResolvedDependencies as $resolvedDependency)
                                            <a href="{{ $resolvedDependency->mod->detailUrl() }}" class="text-cyan-600 dark:text-cyan-400 hover:underline">{{ $resolvedDependency->mod->name }}&nbsp;({{ $resolvedDependency->version }})</a>@if (!$loop->last)
                                                ,
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="py-3 user-markdown text-gray-700 dark:text-gray-400">
                                {{-- The description below is safe to write directly because it has been run though HTMLPurifier during the import process. --}}
                                {!! Str::markdown($version->description) !!}
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Comments --}}
                <div x-show="selectedTab === 'comments'" class="user-markdown p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl text-gray-700 dark:text-gray-400">
                    <p>Not quite yet...</p>
                </div>
            </div>
        </div>

        {{-- Right Column --}}
        <div class="col-span-1 flex flex-col gap-6">

            {{-- Desktop Download Button --}}
            @if ($mod->latestVersion)
                <livewire:mod.download-button :mod="$mod" classes="hidden lg:block"/>
            @endif

            {{-- Additional Mod Details --}}
            <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('Details') }}</h2>
                <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-900 dark:text-gray-100">
                    @if ($mod->license)
                        <li class="px-4 py-4 sm:px-0">
                            <h3>{{ __('License') }}</h3>
                            <p class="truncate">
                                <a href="{{ $mod->license->link }}" title="{{ $mod->license->name }}" target="_blank" class="text-cyan-600 dark:text-cyan-400 hover:underline">
                                    {{ $mod->license->name }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($mod->source_code_link)
                        <li class="px-4 py-4 sm:px-0">
                            <h3>{{ __('Source Code') }}</h3>
                            <p class="truncate">
                                <a href="{{ $mod->source_code_link }}" title="{{ $mod->source_code_link }}" target="_blank" class="text-cyan-600 dark:text-cyan-400 hover:underline">
                                    {{ $mod->source_code_link }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($mod->latestVersion?->virus_total_link)
                        <li class="px-4 py-4 sm:px-0">
                            <h3>{{ __('Latest Version VirusTotal Result') }}</h3>
                            <p class="truncate">
                                <a href="{{ $mod->latestVersion->virus_total_link }}" title="{{ $mod->latestVersion->virus_total_link }}" target="_blank" class="text-cyan-600 dark:text-cyan-400 hover:underline">
                                    {{ $mod->latestVersion->virus_total_link }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($mod->latestVersion?->dependencies->isNotEmpty() && $mod->latestVersion->dependencies->contains(fn($dependency) => $dependency->resolvedVersion?->mod))
                        <li class="px-4 py-4 sm:px-0">
                            <h3>{{ __('Latest Version Dependencies') }}</h3>
                            <p class="truncate">
                                @foreach ($mod->latestVersion->dependencies as $dependency)
                                    <a href="{{ $dependency->resolvedVersion->mod->detailUrl() }}" class="text-cyan-600 dark:text-cyan-400 hover:underline">
                                        {{ $dependency->resolvedVersion->mod->name }}
                                        &nbsp;({{ $dependency->resolvedVersion->version }})
                                    </a><br />
                                @endforeach
                            </p>
                        </li>
                    @endif
                    @if ($mod->contains_ads)
                        <li class="px-4 py-4 sm:px-0 flex flex-row gap-2 items-center">
                            <svg class="grow-0 w-[16px] h-[16px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z" clip-rule="evenodd" />
                            </svg>
                            <h3 class="grow text-gray-900 dark:text-gray-100">
                                {{ __('Includes Advertising') }}
                            </h3>
                        </li>
                    @endif
                    @if ($mod->contains_ai_content)
                        <li class="px-4 py-4 sm:px-0 flex flex-row gap-2 items-center">
                            <svg class="grow-0 w-[16px] h-[16px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z" clip-rule="evenodd" />
                            </svg>
                            <h3 class="grow text-gray-900 dark:text-gray-100">
                                {{ __('Includes AI Generated Content') }}
                            </h3>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

</x-app-layout>
