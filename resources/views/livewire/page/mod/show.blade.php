<x-slot:title>
    {!! __(':mod - Mod Details - The Forge', ['mod' => $mod->name]) !!}
</x-slot>

<x-slot:description>
    {!! __('The details for :mod on The Forge. :teaser', ['mod' => $mod->name, 'teaser' => $mod->teaser]) !!}
</x-slot>

<x-slot:header>
    <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
            {{ __('Mod Details') }}
        </h2>
        @if (auth()->user()?->can('viewActions', [App\Models\Mod::class, $mod]))
            @if (auth()->user()?->hasMfaEnabled())
                <flux:button href="{{ route('mod.version.create', ['mod' => $mod->id]) }}" size="sm">
                    {{ __('Create Mod Version') }}
                </flux:button>
            @else
                <flux:tooltip content="Must enable MFA to create mod versions.">
                    <div>
                        <flux:button disabled="true" size="sm">{{ __('Create Mod Version') }}</flux:button>
                    </div>
                </flux:tooltip>
            @endif
        @endif
    </div>
</x-slot>

<x-slot:openGraphImage>{{ $openGraphImage }}</x-slot>

<div>

    @can('create', [App\Models\ModVersion::class, $mod])
        @if (! $mod->latestVersion)
            <div class="max-w-7xl mx-auto pb-6 px-4 gap-6 sm:px-6 lg:px-8">
                <flux:callout icon="exclamation-triangle" color="orange" inline="inline">
                    <flux:callout.heading>Not Discoverable</flux:callout.heading>
                    <flux:callout.text>In order for this mod to be discoverable by other users you must first create a mod version.</flux:callout.text>
                    <x-slot name="actions" class="@md:h-full m-0!">
                        <flux:button href="{{ route('mod.version.create', ['mod' => $mod->id]) }}">Create Version</flux:button>
                    </x-slot>
                </flux:callout>
            </div>
        @elseif ($mod->latestVersion && (is_null($mod->published_at) || $mod->published_at > now() || !$mod->versions()->whereNotNull('published_at')->where('published_at', '<=', now())->exists()))
            <div class="max-w-7xl mx-auto pb-6 px-4 gap-6 sm:px-6 lg:px-8">
                <flux:callout icon="exclamation-triangle" color="orange" inline="inline">
                    <flux:callout.heading>Not Discoverable</flux:callout.heading>
                    <flux:callout.text>This mod is not yet published or scheduled for future publication. Once the mod (and at least one of its versions) are published, the mod will be become available to the public.</flux:callout.text>
                </flux:callout>
            </div>
        @endif
    @endcan

    <div class="grid grid-cols-1 lg:grid-cols-3 max-w-7xl mx-auto py-6 px-4 gap-6 sm:px-6 lg:px-8">
        <div class="lg:col-span-2 flex flex-col gap-6">

            {{-- Main Mod Details Card --}}
            <div class="relative p-4 sm:p-6 text-center sm:text-left bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none">
                @can('update', $mod)
                    <livewire:mod.action
                        wire:key="mod-action-show-{{ $mod->id }}"
                        :mod-id="$mod->id"
                        :mod-name="$mod->name"
                        :mod-featured="(bool) $mod->featured"
                        :mod-disabled="(bool) $mod->disabled"
                    />
                @endcan

                <livewire:ribbon.mod
                    wire:key="mod-ribbon-show-{{ $mod->id }}"
                    :mod-id="$mod->id"
                    :disabled="$mod->disabled"
                    :published-at="$mod->published_at?->toISOString()"
                    :featured="$mod->featured"
                />

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
                        @if ($mod->owner)
                            <p>{{ __('Created by') }}&nbsp;<a href="{{ $mod->owner->profile_url }}" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">{{ $mod->owner->name }}</a></p>
                        @endif
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
                <x-mod.download-button
                    name="download-show-mobile"
                    :mod-id="$mod->id"
                    :latest-version-id="$mod->latestVersion->id"
                    :download-url="$mod->downloadUrl()"
                    :version-string="$mod->latestVersion->version"
                    :spt-version-formatted="$mod->latestVersion->latestSptVersion?->version_formatted"
                    :spt-version-color-class="$mod->latestVersion->latestSptVersion?->color_class"
                    :version-description-html="$mod->latestVersion->description_html"
                    :version-updated-at="$mod->latestVersion->updated_at"
                />
            @endif

            {{-- Tabs --}}
            <div
                x-data="{ selectedTab: window.location.hash ? (window.location.hash.includes('-comment-') ? window.location.hash.substring(1).split('-comment-')[0] : window.location.hash.substring(1)) : 'description' }"
                x-init="$watch('selectedTab', (tab) => {window.location.hash = tab})"
                class="lg:col-span-2 flex flex-col gap-6"
            >
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
                    {{--
                        !DANGER ZONE!

                        This field is cleaned by the backend, so we can trust it. Other fields are not. Only write out
                        fields like this when you're absolutly sure that the data is safe. Which is almost never.
                     --}}
                    {!! $mod->description_html !!}
                </div>

                {{-- Mod Versions --}}
                <div x-show="selectedTab === 'versions'">
                    @forelse($versions as $version)
                        @can('view', $version)
                            <div wire:key="mod-show-version-{{ $mod->id }}-{{ $version->id }}">
                                <x-mod.version-card :version="$version" />
                            </div>
                        @endcan
                    @empty
                        <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                            <p class="text-gray-900 dark:text-gray-200">{{ __('No versions found.') }}</p>
                        </div>
                    @endforelse
                    {{ $versions->links() }}
                </div>

                {{-- Comments --}}
                <div id="comments" x-show="selectedTab === 'comments'">
                    <livewire:comment-component
                        wire:key="comment-component-{{ $mod->id }}"
                        :commentable="$mod"
                    />
                </div>
            </div>
        </div>

        {{-- Right Column --}}
        <div class="col-span-1 flex flex-col gap-6">

            {{-- Desktop Download Button --}}
            @if ($mod->latestVersion)
                <x-mod.download-button
                    name="download-show-desktop"
                    :mod-id="$mod->id"
                    :latest-version-id="$mod->latestVersion->id"
                    :download-url="$mod->downloadUrl()"
                    :version-string="$mod->latestVersion->version"
                    :spt-version-formatted="$mod->latestVersion->latestSptVersion?->version_formatted"
                    :spt-version-color-class="$mod->latestVersion->latestSptVersion?->color_class"
                    :version-description-html="$mod->latestVersion->description_html"
                    :version-updated-at="$mod->latestVersion->updated_at"
                />
            @endif

            {{-- Additional Mod Details --}}
            <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('Details') }}</h2>
                <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-900 dark:text-gray-100 ">
                    @if ($mod->authors->isNotEmpty())
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3>{{ __('Additional Authors') }}</h3>
                            <p class="truncate">
                                @foreach ($mod->authors->sortDesc() as $user)
                                    <a href="{{ $user->profile_url }}" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">{{ $user->name }}</a>{{ $loop->last ? '' : ',' }}
                                @endforeach
                            </p>
                        </li>
                    @endif
                    @if ($mod->license)
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3>{{ __('License') }}</h3>
                            <p class="truncate">
                                <a href="{{ $mod->license->link }}" title="{{ $mod->license->name }}" target="_blank" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                                    {{ $mod->license->name }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($mod->source_code_url)
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3>{{ __('Source Code') }}</h3>
                            <p class="truncate">
                                <a href="{{ $mod->source_code_url }}" title="{{ $mod->source_code_url }}" target="_blank" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                                    {{ $mod->source_code_url }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($mod->latestVersion?->virus_total_link)
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3>{{ __('Latest Version VirusTotal Result') }}</h3>
                            <p class="truncate">
                                <a href="{{ $mod->latestVersion->virus_total_link }}" title="{{ $mod->latestVersion->virus_total_link }}" target="_blank" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                                    {{ $mod->latestVersion->virus_total_link }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($mod->latestVersion?->dependencies->isNotEmpty() && $mod->latestVersion->dependencies->contains(fn($dependency) => $dependency->resolvedVersion?->mod))
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3>{{ __('Latest Version Dependencies') }}</h3>
                            <p class="truncate">
                                @foreach ($mod->latestVersion->dependencies as $dependency)
                                    <a href="{{ $dependency->resolvedVersion->mod->detail_url }}" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                                        {{ $dependency->resolvedVersion->mod->name }}
                                        &nbsp;({{ $dependency->resolvedVersion->version }})
                                    </a><br />
                                @endforeach
                            </p>
                        </li>
                    @endif
                    @if ($mod->contains_ads)
                        <li class="px-4 py-4 last:pb-0 sm:px-0 flex flex-row gap-2 items-center">
                            <svg class="grow-0 w-[16px] h-[16px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z" clip-rule="evenodd" />
                            </svg>
                            <h3 class="grow text-gray-900 dark:text-gray-100">
                                {{ __('Includes Advertising') }}
                            </h3>
                        </li>
                    @endif
                    @if ($mod->contains_ai_content)
                        <li class="px-4 py-4 last:pb-0 sm:px-0 flex flex-row gap-2 items-center">
                            <svg class="grow-0 w-[16px] h-[16px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z" clip-rule="evenodd" />
                            </svg>
                            <h3 class="grow text-gray-900 dark:text-gray-100">
                                {{ __('Includes AI Generated Content') }}
                            </h3>
                        </li>
                    @endif
                </ul>
                <livewire:report-component variant="link" :reportable-id="$mod->id" :reportable-type="get_class($mod)" />
            </div>
        </div>
    </div>
</div>
