<x-slot:title>
    {!! __(':mod - Mod Details - The Forge', ['mod' => $mod->name]) !!}
</x-slot>

<x-slot:description>
    {!! __('The details for :mod on The Forge. :teaser', ['mod' => $mod->name, 'teaser' => $mod->teaser]) !!}
</x-slot>

<x-slot:header>
    <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
            <flux:icon.cube-transparent class="w-5 h-5" />
            {{ __('Mod Details') }}
        </h2>
        <div class="flex items-center gap-2">
            @auth
                @if ($mod->addons_enabled)
                    @if (auth()->user()->hasMfaEnabled())
                        <flux:button
                            href="{{ route('addon.guidelines', ['mod' => $mod->id]) }}"
                            size="sm"
                        >{{ __('Create Addon') }}</flux:button>
                    @else
                        <flux:tooltip content="Must enable MFA to create addons.">
                            <div>
                                <flux:button
                                    disabled="true"
                                    size="sm"
                                >{{ __('Create Addon') }}</flux:button>
                            </div>
                        </flux:tooltip>
                    @endif
                @endif
            @endauth
            @if (auth()->user()
                    ?->can('viewActions', [App\Models\Mod::class, $mod]))
                @if (auth()->user()?->hasMfaEnabled())
                    <flux:button
                        href="{{ route('mod.version.create', ['mod' => $mod->id]) }}"
                        size="sm"
                    >
                        {{ __('Create Mod Version') }}
                    </flux:button>
                @else
                    <flux:tooltip content="Must enable MFA to create mod versions.">
                        <div>
                            <flux:button
                                disabled="true"
                                size="sm"
                            >{{ __('Create Mod Version') }}</flux:button>
                        </div>
                    </flux:tooltip>
                @endif
            @endif
        </div>
    </div>
</x-slot>

@if ($openGraphImage)
    <x-slot:openGraphImage>{{ $openGraphImage }}</x-slot>
@endif

<div>

    @if ($shouldShowWarnings && !empty($warningMessages))
        <div class="max-w-7xl mx-auto pb-6 px-4 gap-6 sm:px-6 lg:px-8">
            <flux:callout
                icon="exclamation-triangle"
                color="orange"
                inline="inline"
            >
                <flux:callout.heading>Visibility Warning</flux:callout.heading>
                <flux:callout.text>
                    @foreach ($warningMessages as $warning)
                        <div>{{ $warning }}</div>
                    @endforeach
                </flux:callout.text>
                @cachedCan('create', [App\Models\ModVersion::class, $mod])
                    @if (isset($warningMessages['no_versions']))
                        <x-slot
                            name="actions"
                            class="@md:h-full m-0!"
                        >
                            <flux:button href="{{ route('mod.version.create', ['mod' => $mod->id]) }}">Create Version
                            </flux:button>
                        </x-slot>
                    @endif
                @endcachedCan
            </flux:callout>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 max-w-7xl mx-auto py-6 px-4 gap-6 sm:px-6 lg:px-8">
        <div class="lg:col-span-2 flex flex-col gap-6">

            {{-- Main Mod Details Card --}}
            <div
                class="relative p-4 sm:p-6 text-center sm:text-left bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none">
                @cachedCan('update', $mod)
                    <livewire:mod.action
                        wire:key="mod-action-show-{{ $mod->id }}"
                        :mod-id="$mod->id"
                        :mod-name="$mod->name"
                        :mod-featured="(bool) $mod->featured"
                        :mod-disabled="(bool) $mod->disabled"
                        :mod-published="(bool) $mod->published_at && $mod->published_at <= now()"
                    />
                @endcachedCan

                <livewire:ribbon.mod
                    wire:key="mod-ribbon-show-{{ $mod->id }}"
                    :mod-id="$mod->id"
                    :disabled="$mod->disabled"
                    :published-at="$mod->published_at?->toISOString()"
                    :featured="$mod->featured"
                    :publicly-visible="$mod->isPubliclyVisible()"
                />

                <div class="flex flex-col sm:flex-row gap-4 sm:gap-6">
                    <div class="grow-0 shrink-0 flex justify-center items-center">
                        @if ($mod->thumbnail)
                            <img
                                src="{{ $mod->thumbnailUrl }}"
                                alt="{{ $mod->name }}"
                                class="w-36 rounded-lg"
                            >
                        @else
                            <div
                                class="w-36 h-36 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center">
                                <flux:icon.cube-transparent class="w-20 h-20 text-gray-400 dark:text-gray-600" />
                            </div>
                        @endif
                    </div>
                    <div
                        class="grow flex flex-col justify-center items-center sm:items-start text-gray-900 dark:text-gray-200">
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
                            <p>
                                {{ __('Created by') }}
                                <a
                                    href="{{ $mod->owner->profile_url }}"
                                    class="hover:text-black dark:hover:text-white"
                                ><x-user-name
                                        :user="$mod->owner"
                                        class="underline"
                                    /></a>
                            </p>
                        @endif
                        <p title="{{ __('Exactly') }} {{ $mod->downloads }}">{{ Number::downloads($mod->downloads) }}
                            {{ __(Str::plural('Download', $mod->downloads)) }}</p>
                        @if ($mod->latestVersion?->latestSptVersion)
                            <p class="mt-2">
                                <span
                                    class="badge-version {{ $mod->latestVersion->latestSptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap"
                                >
                                    {{ $mod->latestVersion->latestSptVersion->version_formatted }}
                                    {{ __('Compatible') }}
                                </span>
                            </p>
                        @else
                            <p class="mt-2">
                                <span
                                    class="badge-version bg-gray-200 text-gray-700 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap"
                                >
                                    {{ __('Unknown SPT Version') }}
                                </span>
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Mod Teaser --}}
                @if ($mod->teaser)
                    <p
                        class="mt-6 pt-3 border-t-2 border-gray-300 dark:border-gray-800 text-gray-900 dark:text-gray-200">
                        {{ $mod->teaser }}</p>
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
                    :file-size="$mod->latestVersion->formatted_file_size"
                />
            @endif

            {{-- Tabs --}}
            <div
                x-data="{ selectedTab: window.location.hash ? (window.location.hash.includes('-comment-') ? window.location.hash.substring(1).split('-comment-')[0] : window.location.hash.substring(1)) : 'description' }"
                x-init="$watch('selectedTab', (tab) => { window.location.hash = tab })"
                class="lg:col-span-2 flex flex-col gap-6"
            >
                <div>
                    {{-- Mobile Dropdown --}}
                    <div class="sm:hidden">
                        <label
                            for="tabs"
                            class="sr-only"
                        >{{ __('Select a tab') }}</label>
                        <select
                            id="tabs"
                            name="tabs"
                            x-model="selectedTab"
                            class="block w-full rounded-md dark:text-white bg-gray-100 dark:bg-gray-950 border-gray-300 dark:border-gray-700 focus:border-cyan-500 dark:focus:border-cyan-400 focus:ring-cyan-500 dark:focus:ring-cyan-400"
                        >
                            <option value="description">{{ __('Description') }}</option>
                            <option value="versions">{{ $versionCount }}
                                {{ __(Str::plural('Version', $versionCount)) }}</option>
                            @if ($mod->addons_enabled)
                                <option value="addons">{{ $addonCount }}
                                    {{ __(Str::plural('Addon', $addonCount)) }}</option>
                            @endif
                            @if (!$mod->comments_disabled || auth()->user()?->isModOrAdmin() || $mod->isAuthorOrOwner(auth()->user()))
                                <option value="comments">{{ $commentCount }}
                                    {{ __(Str::plural('Comment', $commentCount)) }}</option>
                            @endif
                        </select>
                    </div>

                    {{-- Desktop Tabs --}}
                    <div class="hidden sm:block">
                        <nav
                            class="isolate flex divide-x divide-gray-300 dark:divide-gray-800 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl"
                            aria-label="Tabs"
                        >
                            <x-tab-button name="Description" />
                            <x-tab-button
                                name="Versions"
                                value="versions"
                                :label="$versionCount . ' ' . Str::plural('Version', $versionCount)"
                            />
                            @if ($mod->addons_enabled)
                                <x-tab-button
                                    name="Addons"
                                    value="addons"
                                    :label="$addonCount . ' ' . Str::plural('Addon', $addonCount)"
                                />
                            @endif
                            @if (!$mod->comments_disabled || auth()->user()?->isModOrAdmin() || $mod->isAuthorOrOwner(auth()->user()))
                                <x-tab-button
                                    name="Comments"
                                    value="comments"
                                    :label="$commentCount . ' ' . Str::plural('Comment', $commentCount)"
                                />
                            @endif
                        </nav>
                    </div>
                </div>

                {{-- Mod Description --}}
                <div
                    x-show="selectedTab === 'description'"
                    class="user-markdown p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl"
                >
                    {{--
                        !DANGER ZONE!

                        This field is cleaned by the backend, so we can trust it. Other fields are not. Only write out
                        fields like this when you're absolutely sure that the data is safe. Which is almost never.
                     --}}
                    {!! $mod->description_html !!}
                </div>

                {{-- Mod Versions --}}
                <div x-show="selectedTab === 'versions'">
                    @forelse($versions as $version)
                        @cachedCan('view', $version)
                            <div wire:key="mod-show-version-{{ $mod->id }}-{{ $version->id }}">
                                <x-mod.version-card :version="$version" />
                            </div>
                        @endcachedCan
                    @empty
                        <div
                            class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                            <div class="text-center py-8">
                                <flux:icon.archive-box class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ __('No Versions Yet') }}</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('This mod doesn\'t have any versions yet.') }}</p>
                                @cachedCan('create', [App\Models\ModVersion::class, $mod])
                                    <div class="mt-6">
                                        <flux:button href="{{ route('mod.version.create', ['mod' => $mod->id]) }}">
                                            {{ __('Create First Version') }}
                                        </flux:button>
                                    </div>
                                @endcachedCan
                            </div>
                        </div>
                    @endforelse
                    {{ $versions->links() }}
                </div>

                {{-- Addons --}}
                <div
                    x-show="selectedTab === 'addons'"
                    x-cloak
                >
                    @if ($mod->addons_enabled)
                        @if ($addonCount > 0)
                            {{-- Version Filter --}}
                            <div class="mb-4 flex items-center justify-between gap-4">
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <span x-show="!$wire.selectedModVersionId">
                                        Select a mod version to filter by on the right.
                                    </span>
                                    <span
                                        x-show="$wire.selectedModVersionId"
                                        x-cloak
                                    >
                                        Showing addons compatible with selected version.
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <label
                                        for="mod-version-filter"
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"
                                    >
                                        Filter by mod version:
                                    </label>
                                    <flux:select
                                        wire:model.live="selectedModVersionId"
                                        id="mod-version-filter"
                                        size="sm"
                                    >
                                        <flux:select.option value="">All versions</flux:select.option>
                                        @foreach ($modVersionsForFilter as $version)
                                            <flux:select.option value="{{ $version->id }}">
                                                v{{ $version->version }}
                                                @if ($version->latestSptVersion)
                                                    ({{ $version->latestSptVersion->version_formatted }})
                                                @endif
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            </div>

                            <div class="grid gap-4">
                                @foreach ($addons as $addon)
                                    <x-addon.card
                                        :addon="$addon"
                                        :selected-mod-version-id="$selectedModVersionId"
                                        wire:key="addon-card-{{ $addon->id }}"
                                    />
                                @endforeach
                            </div>
                            {{ $addons->links() }}
                        @else
                            <div
                                class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                                <div class="text-center py-8">
                                    <flux:icon.puzzle-piece class="mx-auto size-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ __('No Addons Yet') }}</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('This mod doesn\'t have any addons yet.') }}</p>
                                    @cachedCan('create', [App\Models\Addon::class, $mod])
                                        <div class="mt-6">
                                            <flux:button href="{{ route('addon.guidelines', ['mod' => $mod->id]) }}">
                                                {{ __('Create First Addon') }}
                                            </flux:button>
                                        </div>
                                    @endcachedCan
                                </div>
                            </div>
                        @endif
                    @else
                        <div
                            class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                            <flux:callout
                                icon="information-circle"
                                color="zinc"
                            >
                                <flux:callout.heading>{{ __('Addons Disabled') }}</flux:callout.heading>
                                <flux:callout.text>
                                    {{ __('The mod owner has disabled addons for this mod.') }}
                                </flux:callout.text>
                            </flux:callout>
                        </div>
                    @endif
                </div>

                {{-- Comments --}}
                @if (!$mod->comments_disabled || auth()->user()?->isModOrAdmin() || $mod->isAuthorOrOwner(auth()->user()))
                    <div
                        id="comments"
                        x-show="selectedTab === 'comments'"
                    >
                        @if ($mod->comments_disabled && (auth()->user()?->isModOrAdmin() || $mod->isAuthorOrOwner(auth()->user())))
                            <div class="mb-6">
                                <flux:callout
                                    icon="exclamation-triangle"
                                    color="orange"
                                    inline="inline"
                                >
                                    <flux:callout.text>
                                        {{ __('Comments have been disabled for this mod and are not visible to normal users. As :role, you can still view and manage all comments.', ['role' => auth()->user()?->isModOrAdmin() ? 'an administrator or moderator' : 'the mod owner or author']) }}
                                    </flux:callout.text>
                                </flux:callout>
                            </div>
                        @endif
                        <livewire:comment-component
                            wire:key="comment-component-{{ $mod->id }}"
                            :commentable="$mod"
                        />
                    </div>
                @endif
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
                    :file-size="$mod->latestVersion->formatted_file_size"
                />
            @endif

            {{-- Profile Binding Notice --}}
            @if ($requiresProfileBindingNotice)
                <div
                    class="p-3 sm:p-4 bg-amber-500 dark:bg-amber-600 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                    <div class="flex gap-3 items-center">
                        <div class="flex-shrink-0">
                            <flux:icon.exclamation-triangle
                                variant="mini"
                                class="size-5 text-black dark:text-white"
                            />
                        </div>
                        <div class="text-sm font-medium text-black dark:text-white">
                            <strong>Notice:</strong> This mod <em>may</em> make permanent changes to your profile, and
                            <em>may</em> not be removable without starting a new profile. <a
                                href="https://wiki.sp-tarkov.com/Profiles#mods"
                                target="_blank"
                                class="underline text-black hover:text-orange-800 dark:text-white dark:hover:text-white"
                            >More information.</a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Additional Mod Details --}}
            <div
                class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('Details') }}</h2>
                <ul
                    role="list"
                    class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-900 dark:text-gray-100 "
                >
                    <li class="px-4 py-4 last:pb-0 sm:px-0">
                        <h3 class="font-bold">{{ __('GUID') }}</h3>
                        <p class="flex items-center gap-2">
                            @if ($mod->guid)
                                <span
                                    class="font-mono text-sm truncate"
                                    title="{{ $mod->guid }}"
                                >{{ $mod->guid }}</span>
                                <button
                                    x-data="{ copied: false }"
                                    @click="navigator.clipboard.writeText('{{ $mod->guid }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="inline-flex items-center justify-center w-4 h-4 flex-shrink-0 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                    title="Copy GUID"
                                >
                                    <flux:icon.clipboard-document
                                        x-show="!copied"
                                        class="size-4"
                                    />
                                    <flux:icon.check
                                        x-show="copied"
                                        x-cloak
                                        class="size-4"
                                    />
                                </button>
                            @else
                                <span class="text-gray-500 dark:text-gray-400 italic">{{ __('Not Available') }}</span>
                            @endif
                        </p>
                    </li>
                    @if ($mod->authors->isNotEmpty())
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Additional Authors') }}</h3>
                            <p class="truncate [&>span:not(:last-child)]:after:content-[',_']">
                                @foreach ($mod->authors->sortDesc() as $user)
                                    <span><a
                                            href="{{ $user->profile_url }}"
                                            class="hover:text-black dark:hover:text-white"
                                        ><x-user-name
                                                :user="$user"
                                                class="underline"
                                            /></a></span>
                                @endforeach
                            </p>
                        </li>
                    @endif
                    @if ($mod->category)
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Category') }}</h3>
                            <p class="truncate">
                                <a
                                    href="{{ route('mods', ['category' => $mod->category->slug]) }}"
                                    class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                >
                                    {{ $mod->category->title }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($mod->license)
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('License') }}</h3>
                            <p class="truncate">
                                <a
                                    href="{{ $mod->license->link }}"
                                    title="{{ $mod->license->name }}"
                                    target="_blank"
                                    class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                >
                                    {{ $mod->license->name }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($mod->sourceCodeLinks->isNotEmpty())
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Source Code') }}</h3>
                            @foreach ($mod->sourceCodeLinks as $link)
                                <p class="truncate">
                                    @if ($link->label !== '')
                                        <span class="text-gray-800 dark:text-gray-200">{{ $link->label }}:</span>
                                        <a
                                            href="{{ $link->url }}"
                                            title="{{ $link->url }}"
                                            target="_blank"
                                            class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                        >
                                            {{ $link->url }}
                                        </a>
                                    @else
                                        <a
                                            href="{{ $link->url }}"
                                            title="{{ $link->url }}"
                                            target="_blank"
                                            class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                        >
                                            {{ $link->url }}
                                        </a>
                                    @endif
                                </p>
                            @endforeach
                        </li>
                    @endif
                    @if ($mod->latestVersion?->virus_total_link)
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Latest Version VirusTotal Result') }}</h3>
                            <p class="truncate">
                                <a
                                    href="{{ $mod->latestVersion->virus_total_link }}"
                                    title="{{ $mod->latestVersion->virus_total_link }}"
                                    target="_blank"
                                    class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                >
                                    {{ $mod->latestVersion->virus_total_link }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if (
                        $mod->latestVersion?->dependencies->isNotEmpty() &&
                            $mod->latestVersion->dependencies->contains(fn($dependency) => $dependency->resolvedVersion?->mod))
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Latest Version Dependencies') }}</h3>
                            <p class="truncate">
                                @foreach ($mod->latestVersion->dependencies as $dependency)
                                    <a
                                        href="{{ $dependency->resolvedVersion->mod->detail_url }}"
                                        class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                    >
                                        {{ $dependency->resolvedVersion->mod->name }}
                                        &nbsp;({{ $dependency->resolvedVersion->version }})
                                    </a><br />
                                @endforeach
                            </p>
                        </li>
                    @endif
                    @if ($mod->contains_ads)
                        <li class="px-4 py-4 last:pb-0 sm:px-0 flex flex-row gap-2 items-center">
                            <flux:icon.check-circle
                                variant="micro"
                                class="grow-0 size-4"
                            />
                            <h3 class="grow text-gray-900 dark:text-gray-100">
                                {{ __('Includes Advertising') }}
                            </h3>
                        </li>
                    @endif
                    @if ($mod->contains_ai_content)
                        <li class="px-4 py-4 last:pb-0 sm:px-0 flex flex-row gap-2 items-center">
                            <flux:icon.check-circle
                                variant="micro"
                                class="grow-0 size-4"
                            />
                            <h3 class="grow text-gray-900 dark:text-gray-100">
                                {{ __('Includes AI Generated Content') }}
                            </h3>
                        </li>
                    @endif
                </ul>
                <livewire:report-component
                    variant="link"
                    :reportable-id="$mod->id"
                    :reportable-type="get_class($mod)"
                />
            </div>
        </div>
    </div>
</div>
