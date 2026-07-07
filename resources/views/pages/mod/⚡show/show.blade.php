<x-slot:title>
    {!! __(':mod - Mod Details - The Forge', ['mod' => $mod->name]) !!}
</x-slot>

<x-slot:description>
    {!! __('The details for :mod on The Forge. :teaser', ['mod' => $mod->name, 'teaser' => $mod->teaser]) !!}
</x-slot>

<x-slot:header>
    <div class="flex w-full items-center justify-between">
        <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-gray-200">
            <flux:icon.cube-transparent class="h-5 w-5" />
            {{ __('Mod Details') }}
        </h2>
        <div class="flex items-center gap-2">
            @auth
                <livewire:mod-add-to-list
                    :source-id="$mod->id"
                    source-type="mod"
                    wire:key="mod-show-add-to-list-{{ $mod->id }}"
                />
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

    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 py-6 sm:px-6 lg:grid-cols-3 lg:px-8">
        <div class="flex flex-col gap-6 lg:col-span-2">

            {{-- Main Mod Details Card --}}
            <div
                class="relative rounded-xl bg-gray-950 p-4 text-center shadow-md shadow-gray-950 drop-shadow-2xl filter-none sm:p-6 sm:text-left">
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

                <div class="flex flex-col gap-4 sm:flex-row sm:gap-6">
                    <div class="flex shrink-0 grow-0 items-center justify-center">
                        @if ($mod->thumbnail)
                            <img
                                src="{{ $mod->thumbnailUrl }}"
                                alt="{{ $mod->name }}"
                                class="w-36 rounded-lg"
                            >
                        @else
                            <div class="flex h-36 w-36 items-center justify-center rounded-lg bg-gray-800">
                                <flux:icon.cube-transparent class="h-20 w-20 text-gray-600" />
                            </div>
                        @endif
                    </div>
                    <div class="flex grow flex-col items-center justify-center text-gray-200 sm:items-start">
                        <div class="flex items-center justify-between space-x-3">
                            <h2 @class([
                                'pb-1 sm:pb-2 text-3xl font-bold text-white',
                                'sm:pr-12' => Gate::check('update', $mod),
                            ])>
                                {{ $mod->name }}
                                @if ($displayVersion)
                                    <span class="text-nowrap font-light text-gray-400">
                                        {{ $displayVersion->version }}
                                    </span>
                                @endif
                            </h2>
                        </div>
                        @if ($mod->owner)
                            <p>
                                {{ __('Created by') }}
                                <a
                                    href="{{ $mod->owner->profile_url }}"
                                    class="hover:text-white"
                                ><x-user-name
                                        :user="$mod->owner"
                                        class="underline"
                                    /></a>
                            </p>
                        @endif
                        <p title="{{ __('Exactly') }} {{ $mod->downloads }}">{{ Number::downloads($mod->downloads) }}
                            {{ __(Str::plural('Download', $mod->downloads)) }}</p>
                        <p class="mt-2 flex flex-wrap items-center gap-2">
                            @if ($displayVersion?->latestSptVersion)
                                <span
                                    class="badge-version {{ $displayVersion->latestSptVersion->color_class }} inline-flex items-center text-nowrap rounded-md px-2 py-1 text-xs font-medium"
                                >
                                    {{ $displayVersion->latestSptVersion->version_formatted }}
                                    {{ __('Compatible') }}
                                </span>
                            @elseif ($displayVersion && $displayVersion->spt_version_constraint === '')
                                <span
                                    class="badge-version gray inline-flex items-center text-nowrap rounded-md px-2 py-1 text-xs font-medium"
                                >
                                    {{ __('Legacy SPT Version') }}
                                </span>
                            @else
                                <span
                                    class="badge-version inline-flex items-center text-nowrap rounded-md bg-gray-200 px-2 py-1 text-xs font-medium text-gray-700"
                                >
                                    {{ __('Unknown SPT Version') }}
                                </span>
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Mod Teaser --}}
                @if ($mod->teaser)
                    <p class="mt-6 border-t-2 border-gray-800 pt-3 text-gray-200">
                        {{ $mod->teaser }}</p>
                @endif
            </div>

            {{-- Mobile Download Button --}}
            @if ($displayVersion)
                <x-mod.download-button
                    name="download-show-mobile"
                    :mod-id="$mod->id"
                    :latest-version-id="$displayVersion->id"
                    :download-url="$displayVersion->downloadUrl()"
                    :version-string="$displayVersion->version"
                    :spt-version-formatted="$displayVersion->latestSptVersion?->version_formatted ?? ($displayVersion->spt_version_constraint === '' ? __('Legacy') : null)"
                    :spt-version-color-class="$displayVersion->latestSptVersion?->color_class ?? ($displayVersion->spt_version_constraint === '' ? 'gray' : null)"
                    :version-description-html="$displayVersion->description_html"
                    :version-updated-at="$displayVersion->updated_at"
                    :file-size="$displayVersion->formatted_file_size"
                    :dependencies="$displayVersion->latestDependenciesResolved"
                />
            @endif

            {{-- Mobile Cheat Notice Warning --}}
            @if ($requiresCheatNotice)
                <div class="rounded-xl bg-red-700 p-3 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-4 lg:hidden">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 pt-0.5">
                            <flux:icon.exclamation-triangle
                                variant="mini"
                                class="size-5 text-white"
                            />
                        </div>
                        <div class="text-sm font-medium text-white">
                            <strong>Warning:</strong> This mod provides functionality similar to traditional multiplayer
                            cheats but was designed exclusively for use with SPT. Attempting to use this software on
                            live EFT servers will not work and will result in an immediate and permanent ban from EFT
                            and SPT. See our <a
                                href="{{ route('static.content-guidelines') }}#anti-cheat-policy"
                                target="_blank"
                                class="text-white underline hover:text-red-200"
                            >Content Guidelines</a> for more information.
                        </div>
                    </div>
                </div>
            @endif

            {{-- Tabs --}}
            <div
                x-data="{ selectedTab: window.location.hash ? (window.location.hash.includes('-comment-') ? window.location.hash.substring(1).split('-comment-')[0] : window.location.hash.substring(1)) : 'description' }"
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
                            @if ($mod->addons_enabled)
                                <flux:select.option value="addons">{{ $addonCount }}
                                    {{ __(Str::plural('Addon', $addonCount)) }}</flux:select.option>
                            @endif
                            @if (!$mod->comments_disabled || auth()->user()?->isModOrAdmin() || $mod->isAuthorOrOwner(auth()->user()))
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
                <div x-show="selectedTab === 'description'">
                    <livewire:mod.show.description-tab
                        wire:key="description-tab-{{ $mod->id }}"
                        :mod-id="$mod->id"
                    />
                </div>

                {{-- Mod Versions --}}
                <div x-show="selectedTab === 'versions'">
                    <livewire:mod.show.versions-tab
                        wire:key="versions-tab-{{ $mod->id }}"
                        :mod-id="$mod->id"
                    />
                </div>

                {{-- Addons --}}
                @if ($mod->addons_enabled)
                    <div
                        x-show="selectedTab === 'addons'"
                        x-cloak
                    >
                        <livewire:mod.show.addons-tab
                            wire:key="addons-tab-{{ $mod->id }}"
                            :mod-id="$mod->id"
                        />
                    </div>
                @endif

                {{-- Comments --}}
                @if (!$mod->comments_disabled || auth()->user()?->isModOrAdmin() || $mod->isAuthorOrOwner(auth()->user()))
                    <div
                        x-ref="commentsTab"
                        x-show="selectedTab === 'comments'"
                    >
                        <livewire:mod.show.comments-tab
                            wire:key="comments-tab-{{ $mod->id }}"
                            :mod-id="$mod->id"
                        />
                    </div>
                @endif
            </div>
        </div>

        {{-- Right Column --}}
        <div class="col-span-1 flex flex-col gap-6">

            {{-- Desktop Download Button --}}
            @if ($displayVersion)
                <x-mod.download-button
                    name="download-show-desktop"
                    :mod-id="$mod->id"
                    :latest-version-id="$displayVersion->id"
                    :download-url="$displayVersion->downloadUrl()"
                    :version-string="$displayVersion->version"
                    :spt-version-formatted="$displayVersion->latestSptVersion?->version_formatted ?? ($displayVersion->spt_version_constraint === '' ? __('Legacy') : null)"
                    :spt-version-color-class="$displayVersion->latestSptVersion?->color_class ?? ($displayVersion->spt_version_constraint === '' ? 'gray' : null)"
                    :version-description-html="$displayVersion->description_html"
                    :version-updated-at="$displayVersion->updated_at"
                    :file-size="$displayVersion->formatted_file_size"
                    :dependencies="$displayVersion->latestDependenciesResolved"
                />
            @endif

            {{-- Required Dependencies --}}
            @if ($displayVersion?->latestDependenciesResolved->isNotEmpty())
                <div class="rounded-xl bg-gray-950 p-4 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
                    <h2 class="text-2xl font-bold text-gray-100">
                        {{ $displayVersion->latestDependenciesResolved->count() === 1 ? __('Required Dependency') : __('Required Dependencies') }}
                    </h2>
                    <p class="mb-4 mt-2 text-sm text-gray-400">
                        {{ $displayVersion->latestDependenciesResolved->count() === 1
                            ? __('The latest version of this mod requires the following mod to be installed as well.')
                            : __('The latest version of this mod requires the following mods to be installed as well.') }}
                    </p>
                    <ul
                        role="list"
                        class="divide-y divide-gray-800"
                    >
                        @foreach ($displayVersion->latestDependenciesResolved as $dependency)
                            @continue($dependency->mod === null)
                            <li class="py-3 first:pt-0 last:pb-0">
                                <a
                                    href="{{ route('mod.show', [$dependency->mod->id, $dependency->mod->slug]) }}"
                                    wire:navigate
                                    class="group flex items-center gap-3"
                                >
                                    {{-- Mod Thumbnail --}}
                                    @if ($dependency->mod->thumbnail)
                                        <img
                                            src="{{ $dependency->mod->thumbnailUrl }}"
                                            @if ($dependency->mod->thumbnailSrcset) srcset="{{ $dependency->mod->thumbnailSrcset }}"
                                                sizes="3rem" @endif
                                            alt="{{ $dependency->mod->name }}"
                                            width="192"
                                            height="192"
                                            loading="lazy"
                                            decoding="async"
                                            class="h-12 w-12 flex-shrink-0 rounded-lg object-cover"
                                        >
                                    @else
                                        <div
                                            class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-gray-800">
                                            <flux:icon.cube-transparent class="h-6 w-6 text-gray-600" />
                                        </div>
                                    @endif

                                    {{-- Mod Info --}}
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-semibold text-gray-100 group-hover:text-cyan-400">
                                            {{ $dependency->mod->name }}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            {{ __('Requires') }} v{{ $dependency->version }}
                                            @if ($dependency->mod->owner)
                                                &middot;
                                                <x-user-name :user="$dependency->mod->owner" />
                                            @endif
                                        </p>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Profile Binding Notice --}}
            @if ($requiresProfileBindingNotice)
                <div class="rounded-xl bg-amber-600 p-3 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0">
                            <flux:icon.exclamation-triangle
                                variant="mini"
                                class="size-5 text-white"
                            />
                        </div>
                        <div class="text-sm font-medium text-white">
                            <strong>Notice:</strong> This mod <em>may</em> make permanent changes to your profile, and
                            <em>may</em> not be removable without starting a new profile. <a
                                href="https://wiki.sp-tarkov.com/Profiles#mods"
                                target="_blank"
                                class="text-white underline hover:text-white"
                            >More information.</a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Desktop Cheat Notice Warning --}}
            @if ($requiresCheatNotice)
                <div
                    class="hidden rounded-xl bg-red-700 p-3 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-4 lg:block">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 pt-0.5">
                            <flux:icon.exclamation-triangle
                                variant="mini"
                                class="size-5 text-white"
                            />
                        </div>
                        <div class="text-sm font-medium text-white">
                            <strong>Warning:</strong> This mod provides functionality similar to traditional multiplayer
                            cheats but was designed exclusively for use with SPT. Attempting to use this software on
                            live EFT servers will not work and will result in an immediate and permanent ban from EFT
                            and SPT. See our <a
                                href="{{ route('static.content-guidelines') }}#anti-cheat-policy"
                                target="_blank"
                                class="text-white underline hover:text-red-200"
                            >Content Guidelines</a> for more information.
                        </div>
                    </div>
                </div>
            @endif

            {{-- Additional Mod Details --}}
            <div class="rounded-xl bg-gray-950 p-4 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
                <h2 class="text-2xl font-bold text-gray-100">{{ __('Details') }}</h2>
                <ul
                    role="list"
                    class="divide-y divide-gray-800 text-gray-100"
                >
                    <li class="px-4 py-4 last:pb-0 sm:px-0">
                        <h3 class="font-bold">{{ __('GUID') }}</h3>
                        <p class="flex items-center gap-2">
                            @if ($mod->guid)
                                <span
                                    class="truncate font-mono text-sm"
                                    title="{{ $mod->guid }}"
                                >{{ $mod->guid }}</span>
                                <button
                                    x-data="{ copied: false }"
                                    @click="navigator.clipboard.writeText('{{ $mod->guid }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="inline-flex h-4 w-4 flex-shrink-0 items-center justify-center text-gray-400 hover:text-gray-200"
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
                                <span class="italic text-gray-400">{{ __('Not Available') }}</span>
                            @endif
                        </p>
                    </li>
                    @if ($mod->additionalAuthors->isNotEmpty())
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Additional Authors') }}</h3>
                            <p class="truncate [&>span:not(:last-child)]:after:content-[',_']">
                                @foreach ($mod->additionalAuthors->sortDesc() as $user)
                                    <span><a
                                            href="{{ $user->profile_url }}"
                                            class="hover:text-white"
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
                                    class="text-gray-200 underline hover:text-white"
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
                                    class="text-gray-200 underline hover:text-white"
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
                    @if ($displayVersion?->virusTotalLinks->isNotEmpty())
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Latest Version VirusTotal Results') }}</h3>
                            @foreach ($displayVersion->virusTotalLinks as $virusTotalLink)
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
                    <li class="flex flex-row items-center gap-2 px-4 py-4 last:pb-0 sm:px-0">
                        <flux:icon
                            icon="{{ $fikaStatus->icon() }}"
                            variant="micro"
                            class="{{ $fikaStatus->colorClass() }} size-4 grow-0"
                        />
                        <h3 class="grow text-gray-100">
                            {{ $fikaStatus->modLabel() }}
                        </h3>
                    </li>
                    @if ($mod->contains_ads)
                        <li class="flex flex-row items-center gap-2 px-4 py-4 last:pb-0 sm:px-0">
                            <flux:icon.check-circle
                                variant="micro"
                                class="size-4 grow-0 text-green-500"
                            />
                            <h3 class="grow text-gray-100">
                                {{ __('Includes Advertising') }}
                            </h3>
                        </li>
                    @endif
                    @if ($mod->contains_ai_content)
                        @if ($mod->custom_ai_disclosure)
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
                                    <flux:icon.check-circle
                                        variant="micro"
                                        class="size-4 grow-0 text-green-500"
                                    />
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
                                >{!! $mod->custom_ai_disclosure_html !!}</div>
                            </li>
                        @else
                            <li class="flex flex-row items-center gap-2 px-4 py-4 last:pb-0 sm:px-0">
                                <flux:icon.check-circle
                                    variant="micro"
                                    class="size-4 grow-0 text-green-500"
                                />
                                <h3 class="grow text-gray-100">
                                    {{ __('Includes AI Generated Content') }}
                                </h3>
                            </li>
                        @endif
                    @endif
                </ul>
            </div>

            {{-- List & Favourite Presence --}}
            @if ($presenceSummary)
                <div class="rounded-xl bg-gray-950 p-4 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
                    <p class="text-sm text-gray-400">
                        {{ $presenceSummary['sentence'] }}
                        <span class="italic text-gray-500">{{ $presenceSummary['flavour'] }}</span>
                    </p>
                </div>
            @endif

            <livewire:report-component
                variant="link"
                :reportable-id="$mod->id"
                :reportable-type="get_class($mod)"
            />
        </div>
    </div>
</div>
