<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
            {{ __('Mod Details') }}
        </h2>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 max-w-7xl mx-auto py-6 px-4 gap-6 sm:px-6 lg:px-8">
        <div class="lg:col-span-2 flex flex-col gap-6">

            {{-- Main Mod Details Card --}}
            <div class="relative p-4 sm:p-6 text-center sm:text-left bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none">
                @if (auth()->user()?->isModOrAdmin())
                    <livewire:mod.moderation wire:key="mod-moderation-show-{{ $this->mod->id }}" :mod="$this->mod" />
                @endif

                <livewire:ribbon
                    wire:key="mod-ribbon-show-{{ $this->mod->id }}"
                    :id="$this->mod->id"
                    :disabled="$this->mod->disabled"
                    :featured="$this->mod->featured"
                />

                <div class="flex flex-col sm:flex-row gap-4 sm:gap-6">
                    <div class="grow-0 shrink-0 flex justify-center items-center">
                        @if ($this->mod->thumbnail)
                            <img src="{{ $this->mod->thumbnailUrl }}" alt="{{ $this->mod->name }}" class="w-36 rounded-lg">
                        @else
                            <img src="https://placehold.co/144x144/31343C/EEE?font=source-sans-pro&text={{ urlencode($this->mod->name) }}" alt="{{ $this->mod->name }}" class="w-36 rounded-lg">
                        @endif
                    </div>
                    <div class="grow flex flex-col justify-center items-center sm:items-start text-gray-900 dark:text-gray-200">
                        <div class="flex justify-between items-center space-x-3">
                            <h2 class="pb-1 sm:pb-2 text-3xl font-bold text-gray-900 dark:text-white">
                                {{ $this->mod->name }}
                                @if ($this->mod->latestVersion)
                                    <span class="font-light text-nowrap text-gray-600 dark:text-gray-400">
                                        {{ $this->mod->latestVersion->version }}
                                    </span>
                                @endif
                            </h2>
                        </div>
                        <p>
                            {{ __('Created by') }}
                            @foreach ($this->mod->users as $user)
                                <a href="{{ $user->profile_url }}" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">{{ $user->name }}</a>{{ $loop->last ? '' : ',' }}
                            @endforeach
                        </p>
                        <p title="{{ __('Exactly') }} {{ $this->mod->downloads }}">{{ Number::downloads($this->mod->downloads) }} {{ __('Downloads') }}</p>
                        @if ($this->mod->latestVersion?->latestSptVersion)
                            <p class="mt-2">
                                <span class="badge-version {{ $this->mod->latestVersion->latestSptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
                                    {{ $this->mod->latestVersion->latestSptVersion->version_formatted }} {{ __('Compatible') }}
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
                @if ($this->mod->teaser)
                    <p class="mt-6 pt-3 border-t-2 border-gray-300 dark:border-gray-800 text-gray-900 dark:text-gray-200">{{ $this->mod->teaser }}</p>
                @endif
            </div>

            {{-- Mobile Download Button --}}
            @if ($this->mod->latestVersion)
                <livewire:mod.download-button key="mod-download-button-mobile" :mod="$this->mod" classes="block lg:hidden" />
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
                    {!! Str::markdown($this->mod->description) !!}
                </div>

                {{-- Mod Versions --}}
                <div x-show="selectedTab === 'versions'">
                    @foreach($this->versions as $version)
                        @can('view', $version)
                            <div wire:key="mod-show-version-{{ $this->mod->id }}-{{ $version->id }}">
                                <x-mod.version-card :version="$version" />
                            </div>
                        @endcan
                    @endforeach
                    {{ $this->versions->links() }}
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
            @if ($this->mod->latestVersion)
                <livewire:mod.download-button key="mod-download-button-desktop" :mod="$this->mod" classes="hidden lg:block" />
            @endif

            {{-- Additional Mod Details --}}
            <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('Details') }}</h2>
                <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-900 dark:text-gray-100">
                    @if ($this->mod->license)
                        <li class="px-4 py-4 sm:px-0">
                            <h3>{{ __('License') }}</h3>
                            <p class="truncate">
                                <a href="{{ $this->mod->license->link }}" title="{{ $this->mod->license->name }}" target="_blank" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                                    {{ $this->mod->license->name }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($this->mod->source_code_link)
                        <li class="px-4 py-4 sm:px-0">
                            <h3>{{ __('Source Code') }}</h3>
                            <p class="truncate">
                                <a href="{{ $this->mod->source_code_link }}" title="{{ $this->mod->source_code_link }}" target="_blank" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                                    {{ $this->mod->source_code_link }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($this->mod->latestVersion?->virus_total_link)
                        <li class="px-4 py-4 sm:px-0">
                            <h3>{{ __('Latest Version VirusTotal Result') }}</h3>
                            <p class="truncate">
                                <a href="{{ $this->mod->latestVersion->virus_total_link }}" title="{{ $this->mod->latestVersion->virus_total_link }}" target="_blank" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                                    {{ $this->mod->latestVersion->virus_total_link }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($this->mod->latestVersion?->dependencies->isNotEmpty() && $this->mod->latestVersion->dependencies->contains(fn($dependency) => $dependency->resolvedVersion?->mod))
                        <li class="px-4 py-4 sm:px-0">
                            <h3>{{ __('Latest Version Dependencies') }}</h3>
                            <p class="truncate">
                                @foreach ($this->mod->latestVersion->dependencies as $dependency)
                                    <a href="{{ $dependency->resolvedVersion->mod->detailUrl() }}" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                                        {{ $dependency->resolvedVersion->mod->name }}
                                        &nbsp;({{ $dependency->resolvedVersion->version }})
                                    </a><br />
                                @endforeach
                            </p>
                        </li>
                    @endif
                    @if ($this->mod->contains_ads)
                        <li class="px-4 py-4 sm:px-0 flex flex-row gap-2 items-center">
                            <svg class="grow-0 w-[16px] h-[16px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z" clip-rule="evenodd" />
                            </svg>
                            <h3 class="grow text-gray-900 dark:text-gray-100">
                                {{ __('Includes Advertising') }}
                            </h3>
                        </li>
                    @endif
                    @if ($this->mod->contains_ai_content)
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
</div>
