<x-slot:title>
    {!! __(':addon - Addon Details - The Forge', ['addon' => $addon->name]) !!}
</x-slot>

<x-slot:description>
    {!! __('The details for :addon addon on The Forge. :teaser', [
        'addon' => $addon->name,
        'teaser' => $addon->teaser,
    ]) !!}
</x-slot>

<x-slot:header>
    <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
            <flux:icon.puzzle-piece class="w-5 h-5" />
            {{ __('Addon Details') }}
        </h2>
        @if (auth()->user()
                ?->can('viewActions', [App\Models\Addon::class, $addon]))
            @if (auth()->user()?->hasMfaEnabled())
                <flux:button
                    href="{{ route('addon.version.create', ['addon' => $addon->id]) }}"
                    size="sm"
                >
                    {{ __('Create Addon Version') }}
                </flux:button>
            @else
                <flux:tooltip content="Must enable MFA to create addon versions.">
                    <div>
                        <flux:button
                            disabled="true"
                            size="sm"
                        >{{ __('Create Addon Version') }}</flux:button>
                    </div>
                </flux:tooltip>
            @endif
        @endif
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
                @cachedCan('create', [App\Models\AddonVersion::class, $addon])
                    @if (isset($warningMessages['no_versions']))
                        <x-slot
                            name="actions"
                            class="@md:h-full m-0!"
                        >
                            <flux:button href="{{ route('addon.version.create', ['addon' => $addon->id]) }}">Create
                                Version
                            </flux:button>
                        </x-slot>
                    @endif
                @endcachedCan
            </flux:callout>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 max-w-7xl mx-auto py-6 px-4 gap-6 sm:px-6 lg:px-8">
        <div class="lg:col-span-2 flex flex-col gap-6">

            {{-- Main Addon Details Card --}}
            <div
                class="relative p-4 sm:p-6 text-center sm:text-left bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none">
                @cachedCan('update', $addon)
                    <livewire:addon.action
                        wire:key="addon-action-show-{{ $addon->id }}"
                        :addon-id="$addon->id"
                        :addon-name="$addon->name"
                        :addon-disabled="(bool) $addon->disabled"
                        :addon-published="(bool) $addon->published_at && $addon->published_at <= now()"
                        :addon-detached="(bool) $addon->detached_at"
                    />
                @endcachedCan

                <livewire:ribbon.addon
                    wire:key="addon-ribbon-show-{{ $addon->id }}"
                    :addon-id="$addon->id"
                    :disabled="$addon->disabled"
                    :published-at="$addon->published_at?->toISOString()"
                    :publicly-visible="$addon->isPubliclyVisible()"
                />

                <div class="flex flex-col sm:flex-row gap-4 sm:gap-6">
                    <div class="grow-0 shrink-0 flex justify-center items-center">
                        @if ($addon->thumbnail)
                            <img
                                src="{{ $addon->thumbnailUrl }}"
                                alt="{{ $addon->name }}"
                                class="w-36 rounded-lg"
                            >
                        @else
                            <div
                                class="w-36 h-36 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center">
                                <flux:icon.puzzle-piece class="w-20 h-20 text-gray-400 dark:text-gray-600" />
                            </div>
                        @endif
                    </div>
                    <div
                        class="grow flex flex-col justify-center items-center sm:items-start text-gray-900 dark:text-gray-200">
                        <div class="flex justify-between items-center space-x-3">
                            <h2 class="pb-1 sm:pb-2 text-3xl font-bold text-gray-900 dark:text-white">
                                {{ $addon->name }}
                                @if ($addon->latestVersion)
                                    <span class="font-light text-nowrap text-gray-600 dark:text-gray-400">
                                        {{ $addon->latestVersion->version }}
                                    </span>
                                @endif
                            </h2>
                        </div>
                        @if ($addon->owner)
                            <p>
                                {{ __('Created by') }}
                                <a
                                    href="{{ $addon->owner->profile_url }}"
                                    class="hover:text-black dark:hover:text-white"
                                ><x-user-name
                                        :user="$addon->owner"
                                        class="underline"
                                    /></a>
                            </p>
                        @endif
                        <p title="{{ __('Exactly') }} {{ $addon->downloads }}">
                            {{ Number::downloads($addon->downloads) }}
                            {{ __(Str::plural('Download', $addon->downloads)) }}</p>
                        @if ($addon->mod)
                            <p class="mt-2">
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ __('Addon for:') }}
                                    <a
                                        href="{{ route('mod.show', [$addon->mod->id, $addon->mod->slug]) }}"
                                        class="underline hover:text-black dark:hover:text-white"
                                    >{{ $addon->mod->name }}</a>
                                </span>
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Addon Teaser --}}
                @if ($addon->teaser)
                    <p
                        class="mt-6 pt-3 border-t-2 border-gray-300 dark:border-gray-800 text-gray-900 dark:text-gray-200">
                        {{ $addon->teaser }}</p>
                @endif
            </div>

            {{-- Mobile Download Button --}}
            @if ($addon->latestVersion)
                <x-addon.download-button
                    name="download-show-mobile"
                    :addon-id="$addon->id"
                    :latest-version-id="$addon->latestVersion->id"
                    :download-url="route('addon.version.download', [
                        $addon->id,
                        $addon->slug,
                        $addon->latestVersion->version,
                    ])"
                    :version-string="$addon->latestVersion->version"
                    :version-description-html="$addon->latestVersion->description_html"
                    :version-updated-at="$addon->latestVersion->updated_at"
                    :file-size="$addon->latestVersion->formatted_file_size"
                />
            @endif

            {{-- Tabs --}}
            <div
                x-data="{ selectedTab: window.location.hash ? (window.location.hash.includes('-comment-') ? window.location.hash.substring(1).split('-comment-')[0] : window.location.hash.substring(1)) : '{{ $addon->description ? 'description' : 'versions' }}' }"
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
                            @if (!$addon->comments_disabled || auth()->user()?->isModOrAdmin() || $addon->isAuthorOrOwner(auth()->user()))
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
                            @if (!$addon->comments_disabled || auth()->user()?->isModOrAdmin() || $addon->isAuthorOrOwner(auth()->user()))
                                <x-tab-button
                                    name="Comments"
                                    value="comments"
                                    :label="$commentCount . ' ' . Str::plural('Comment', $commentCount)"
                                />
                            @endif
                        </nav>
                    </div>
                </div>

                {{-- Addon Description --}}
                <div
                    x-show="selectedTab === 'description'"
                    class="user-markdown p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl"
                >
                    {{--
                        !DANGER ZONE!

                        This field is cleaned by the backend, so we can trust it. Other fields are not. Only write out
                        fields like this when you're absolutely sure that the data is safe. Which is almost never.
                     --}}
                    {!! $addon->description_html !!}
                </div>

                {{-- Addon Versions --}}
                <div x-show="selectedTab === 'versions'">
                    @forelse($versions as $version)
                        <x-addon.version-card
                            wire:key="addon-show-version-{{ $addon->id }}-{{ $version->id }}"
                            :version="$version"
                            :addon="$addon"
                        />
                    @empty
                        <div
                            class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                            <div class="text-center py-8">
                                <flux:icon.archive-box class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ __('No Versions Yet') }}</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('This addon doesn\'t have any versions yet.') }}</p>
                                @cachedCan('create', [App\Models\AddonVersion::class, $addon])
                                    <div class="mt-6">
                                        <flux:button href="{{ route('addon.version.create', ['addon' => $addon->id]) }}">
                                            {{ __('Create First Version') }}
                                        </flux:button>
                                    </div>
                                @endcachedCan
                            </div>
                        </div>
                    @endforelse
                    {{ $versions->links() }}
                </div>

                {{-- Comments --}}
                @if (!$addon->comments_disabled || auth()->user()?->isModOrAdmin() || $addon->isAuthorOrOwner(auth()->user()))
                    <div
                        id="comments"
                        x-show="selectedTab === 'comments'"
                    >
                        @if ($addon->comments_disabled && (auth()->user()?->isModOrAdmin() || $addon->isAuthorOrOwner(auth()->user())))
                            <div class="mb-6">
                                <flux:callout
                                    icon="exclamation-triangle"
                                    color="orange"
                                    inline="inline"
                                >
                                    <flux:callout.text>
                                        {{ __('Comments have been disabled for this addon and are not visible to normal users. As :role, you can still view and manage all comments.', ['role' => auth()->user()?->isModOrAdmin() ? 'an administrator or moderator' : 'the addon owner or author']) }}
                                    </flux:callout.text>
                                </flux:callout>
                            </div>
                        @endif
                        <livewire:comment-component
                            wire:key="comment-component-{{ $addon->id }}"
                            :commentable="$addon"
                        />
                    </div>
                @endif
            </div>
        </div>

        {{-- Right Column --}}
        <div class="col-span-1 flex flex-col gap-6">

            {{-- Desktop Download Button --}}
            @if ($addon->latestVersion)
                <x-addon.download-button
                    name="download-show-desktop"
                    :addon-id="$addon->id"
                    :latest-version-id="$addon->latestVersion->id"
                    :download-url="route('addon.version.download', [
                        $addon->id,
                        $addon->slug,
                        $addon->latestVersion->version,
                    ])"
                    :version-string="$addon->latestVersion->version"
                    :version-description-html="$addon->latestVersion->description_html"
                    :version-updated-at="$addon->latestVersion->updated_at"
                    :file-size="$addon->latestVersion->formatted_file_size"
                />
            @endif

            {{-- Additional Addon Details --}}
            <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('Details') }}</h2>
                <ul
                    role="list"
                    class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-900 dark:text-gray-100 "
                >
                    @if ($addon->mod)
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold flex items-center gap-2">
                                <span>{{ __('Parent Mod') }}</span>
                                @if ($addon->isDetached() && auth()->user()?->isModOrAdmin())
                                    <span
                                        class="inline-block bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 text-[10px] font-semibold px-1.5 py-0.5 rounded uppercase tracking-wide"
                                    >
                                        Detached
                                    </span>
                                @endif
                            </h3>
                            <p class="truncate">
                                <a
                                    href="{{ route('mod.show', [$addon->mod->id, $addon->mod->slug]) }}"
                                    class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                >
                                    {{ $addon->mod->name }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($addon->additionalAuthors->isNotEmpty())
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Additional Authors') }}</h3>
                            <p class="truncate">
                                @foreach ($addon->additionalAuthors->sortDesc() as $user)
                                    <a
                                        href="{{ $user->profile_url }}"
                                        class="underline hover:text-black dark:hover:text-white"
                                    ><x-user-name :user="$user" /></a>{{ $loop->last ? '' : ',' }}
                                @endforeach
                            </p>
                        </li>
                    @endif
                    @if ($addon->license)
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('License') }}</h3>
                            <p class="truncate">
                                <a
                                    href="{{ $addon->license->link }}"
                                    title="{{ $addon->license->name }}"
                                    target="_blank"
                                    class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white"
                                >
                                    {{ $addon->license->name }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if ($addon->sourceCodeLinks->isNotEmpty())
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Source Code') }}</h3>
                            @foreach ($addon->sourceCodeLinks as $link)
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
                    @if ($addon->latestVersion?->virusTotalLinks->isNotEmpty())
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Latest Version VirusTotal Results') }}</h3>
                            @foreach ($addon->latestVersion->virusTotalLinks as $virusTotalLink)
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
                        </li>
                    @endif
                    @if ($addon->contains_ads)
                        <li class="px-4 py-4 last:pb-0 sm:px-0 flex flex-row gap-2 items-center">
                            <svg
                                class="grow-0 w-[16px] h-[16px]"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 16 16"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <h3 class="grow text-gray-900 dark:text-gray-100">
                                {{ __('Includes Advertising') }}
                            </h3>
                        </li>
                    @endif
                    @if ($addon->contains_ai_content)
                        <li class="px-4 py-4 last:pb-0 sm:px-0 flex flex-row gap-2 items-center">
                            <svg
                                class="grow-0 w-[16px] h-[16px]"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 16 16"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <h3 class="grow text-gray-900 dark:text-gray-100">
                                {{ __('Includes AI Generated Content') }}
                            </h3>
                        </li>
                    @endif
                </ul>
                <livewire:report-component
                    variant="link"
                    :reportable-id="$addon->id"
                    :reportable-type="get_class($addon)"
                />
            </div>
        </div>
    </div>
</div>
