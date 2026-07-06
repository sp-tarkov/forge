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
    <div class="flex w-full items-center justify-between">
        <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-gray-200">
            <flux:icon.puzzle-piece class="h-5 w-5" />
            {{ __('Addon Details') }}
        </h2>
        <div class="flex items-center gap-2">
            @auth
                <flux:modal.trigger name="mod-add-to-list-addon-{{ $addon->id }}">
                    <flux:button
                        icon="bookmark-square"
                        size="sm"
                        variant="outline"
                    >{{ __('Add to list') }}</flux:button>
                </flux:modal.trigger>
                <livewire:mod-add-to-list
                    :source-id="$addon->id"
                    source-type="addon"
                    wire:key="addon-show-add-to-list-{{ $addon->id }}"
                />
            @endauth
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
    </div>
</x-slot>

@if ($openGraphImage)
    <x-slot:openGraphImage>{{ $openGraphImage }}</x-slot>
@endif

<div>

    @if ($shouldShowWarnings && !empty($warningMessages))
        <div class="mx-auto max-w-7xl gap-6 px-4 pb-6 sm:px-6 lg:px-8">
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

    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 py-6 sm:px-6 lg:grid-cols-3 lg:px-8">
        <div class="flex flex-col gap-6 lg:col-span-2">

            {{-- Main Addon Details Card --}}
            <div
                class="relative rounded-xl bg-gray-950 p-4 text-center shadow-md shadow-gray-950 drop-shadow-2xl filter-none sm:p-6 sm:text-left">
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

                <div class="flex flex-col gap-4 sm:flex-row sm:gap-6">
                    <div class="flex shrink-0 grow-0 items-center justify-center">
                        @if ($addon->thumbnail)
                            <img
                                src="{{ $addon->thumbnailUrl }}"
                                alt="{{ $addon->name }}"
                                class="w-36 rounded-lg"
                            >
                        @else
                            <div class="flex h-36 w-36 items-center justify-center rounded-lg bg-gray-800">
                                <flux:icon.puzzle-piece class="h-20 w-20 text-gray-600" />
                            </div>
                        @endif
                    </div>
                    <div class="flex grow flex-col items-center justify-center text-gray-200 sm:items-start">
                        <div class="flex items-center justify-between space-x-3">
                            <h2 @class([
                                'pb-1 sm:pb-2 text-3xl font-bold text-white',
                                'sm:pr-12' => Gate::check('update', $addon),
                            ])>
                                {{ $addon->name }}
                                @if ($addon->latestVersion)
                                    <span class="text-nowrap font-light text-gray-400">
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
                                    class="hover:text-white"
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
                                <span class="text-sm text-gray-400">
                                    {{ __('Addon for:') }}
                                    <a
                                        href="{{ route('mod.show', [$addon->mod->id, $addon->mod->slug]) }}"
                                        class="underline hover:text-white"
                                    >{{ $addon->mod->name }}</a>
                                </span>
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Addon Teaser --}}
                @if ($addon->teaser)
                    <p class="mt-6 border-t-2 border-gray-800 pt-3 text-gray-200">
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
                x-init="$watch('selectedTab', (tab) => { window.location.hash = tab });
                if (selectedTab === 'comments' && window.location.hash.includes('-comment-')) {
                    $nextTick(() => {
                        const lazyEl = $refs.commentsTab?.querySelector('[x-intersect]');
                        const expr = lazyEl?.getAttribute('x-intersect');
                        if (lazyEl && expr) window.Alpine.evaluate(lazyEl, expr);
                    });
                }"
                class="flex flex-col gap-6 lg:col-span-2"
            >
                <div>
                    {{-- Mobile Dropdown --}}
                    <div class="sm:hidden">
                        <flux:select
                            variant="listbox"
                            x-model="selectedTab"
                            label:sr-only="{{ __('Select a tab') }}"
                        >
                            <flux:select.option value="description">{{ __('Description') }}</flux:select.option>
                            <flux:select.option value="versions">{{ $versionCount }}
                                {{ __(Str::plural('Version', $versionCount)) }}</flux:select.option>
                            @if (!$addon->comments_disabled || auth()->user()?->isModOrAdmin() || $addon->isAuthorOrOwner(auth()->user()))
                                <flux:select.option value="comments">{{ $commentCount }}
                                    {{ __(Str::plural('Comment', $commentCount)) }}</flux:select.option>
                            @endif
                        </flux:select>
                    </div>

                    {{-- Desktop Tabs --}}
                    <div class="hidden sm:block">
                        <nav
                            class="isolate flex divide-x divide-gray-800 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl"
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
                <div x-show="selectedTab === 'description'">
                    <livewire:addon.show.description-tab
                        wire:key="addon-description-tab-{{ $addon->id }}"
                        :addon-id="$addon->id"
                    />
                </div>

                {{-- Addon Versions --}}
                <div x-show="selectedTab === 'versions'">
                    <livewire:addon.show.versions-tab
                        wire:key="addon-versions-tab-{{ $addon->id }}"
                        :addon-id="$addon->id"
                    />
                </div>

                {{-- Comments --}}
                @if (!$addon->comments_disabled || auth()->user()?->isModOrAdmin() || $addon->isAuthorOrOwner(auth()->user()))
                    <div
                        x-ref="commentsTab"
                        x-show="selectedTab === 'comments'"
                    >
                        <livewire:addon.show.comments-tab
                            wire:key="addon-comments-tab-{{ $addon->id }}"
                            :addon-id="$addon->id"
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
            <div class="rounded-xl bg-gray-950 p-4 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
                <h2 class="text-2xl font-bold text-gray-100">{{ __('Details') }}</h2>
                <ul
                    role="list"
                    class="divide-y divide-gray-800 text-gray-100"
                >
                    @if ($addon->mod)
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="flex items-center gap-2 font-bold">
                                <span>{{ __('Parent Mod') }}</span>
                                @if ($addon->isDetached() && auth()->user()?->isModOrAdmin())
                                    <span
                                        class="inline-block rounded bg-yellow-900/30 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-yellow-200"
                                    >
                                        Detached
                                    </span>
                                @endif
                            </h3>
                            <p class="truncate">
                                <a
                                    href="{{ route('mod.show', [$addon->mod->id, $addon->mod->slug]) }}"
                                    class="text-gray-200 underline hover:text-white"
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
                                        class="underline hover:text-white"
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
                                    class="text-gray-200 underline hover:text-white"
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
                                        <span class="text-gray-200">{{ $link->label }}:</span>
                                        <a
                                            href="{{ $link->url }}"
                                            title="{{ $link->url }}"
                                            target="_blank"
                                            class="text-gray-200 underline hover:text-white"
                                        >
                                            {{ $link->url }}
                                        </a>
                                    @else
                                        <a
                                            href="{{ $link->url }}"
                                            title="{{ $link->url }}"
                                            target="_blank"
                                            class="text-gray-200 underline hover:text-white"
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
                        </li>
                    @endif
                    @if ($addon->contains_ads)
                        <li class="flex flex-row items-center gap-2 px-4 py-4 last:pb-0 sm:px-0">
                            <svg
                                class="h-[16px] w-[16px] grow-0"
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
                            <h3 class="grow text-gray-100">
                                {{ __('Includes Advertising') }}
                            </h3>
                        </li>
                    @endif
                    @if ($addon->contains_ai_content)
                        @if ($addon->custom_ai_disclosure)
                            <li
                                class="px-4 py-4 last:pb-0 sm:px-0"
                                x-data="{ expanded: false }"
                            >
                                <button
                                    type="button"
                                    @click="expanded = !expanded"
                                    :aria-expanded="expanded.toString()"
                                    class="flex w-full cursor-pointer flex-row items-center gap-2 text-left"
                                >
                                    <svg
                                        class="h-[16px] w-[16px] grow-0"
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
                                    <h3 class="grow text-gray-100">
                                        {{ __('Includes AI Generated Content') }}
                                    </h3>
                                    <flux:icon.chevron-up
                                        variant="micro"
                                        class="size-4 grow-0 text-gray-400 transition-transform"
                                        x-bind:class="expanded ? 'rotate-180' : ''"
                                    />
                                </button>
                                <div
                                    x-show="expanded"
                                    x-collapse
                                    class="user-markdown ms-6 mt-2 text-sm text-gray-300"
                                >{!! $addon->custom_ai_disclosure_html !!}</div>
                            </li>
                        @else
                            <li class="flex flex-row items-center gap-2 px-4 py-4 last:pb-0 sm:px-0">
                                <svg
                                    class="h-[16px] w-[16px] grow-0"
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
                                <h3 class="grow text-gray-100">
                                    {{ __('Includes AI Generated Content') }}
                                </h3>
                            </li>
                        @endif
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
