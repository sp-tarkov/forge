<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Traits\Livewire\ModeratesAddon;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component {
    use ModeratesAddon;
    use ModeratesMod;

    /**
     * The mod being shown.
     */
    public Mod $mod;

    /**
     * The Open Graph image for social media sharing.
     */
    public ?string $openGraphImage = null;

    /**
     * Mount the component.
     */
    public function mount(int $modId, string $slug): void
    {
        $this->mod = $this->getMod($modId);

        $this->enforceCanonicalSlug($this->mod, $slug);

        Gate::authorize('view', $this->mod);

        $this->openGraphImage = $this->mod->thumbnail;
    }

    /**
     * Check if the current user should see warnings about this mod.
     */
    public function shouldShowWarnings(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Only show warnings to privileged users (owners, authors, mods, admins)
        return $user->isModOrAdmin() || $this->mod->isAuthorOrOwner($user);
    }

    /**
     * Get the warning messages for this mod.
     *
     * @return array<string, string>
     */
    public function getWarningMessages(): array
    {
        $warnings = [];

        // Check if the mod has no versions at all
        if ($this->mod->versions()->count() === 0) {
            $warnings['no_versions'] = 'This mod has no versions. Users will be unable to view this mod until a version is created.';
        } else {
            // Check if the mod has no published versions
            $publishedVersions = $this->mod->versions()->whereNotNull('published_at')->count();
            $enabledVersions = $this->mod->versions()->where('disabled', false)->count();

            if ($publishedVersions === 0) {
                $warnings['no_published_versions'] = 'This mod has no published versions. Users will be unable to view this mod until a version is published.';
            }

            if ($enabledVersions === 0) {
                $warnings['no_enabled_versions'] = 'This mod has no enabled versions. Users will be unable to view this mod until a version is enabled.';
            }

            // Check if the mod has no publicly visible versions (only if there are versions)
            if (!$this->hasPublicVersions()) {
                $user = auth()->user();
                $isPrivilegedUser = $user && ($this->mod->isAuthorOrOwner($user) || $user->isModOrAdmin());

                if ($isPrivilegedUser) {
                    $warnings['no_valid_spt_versions'] = 'This mod has no valid published SPT versions. Users will be unable to view this mod until a version with valid SPT compatibility is published and enabled.';
                }
            }
        }

        // Check if the mod itself is unpublished
        if (!$this->mod->published_at) {
            $warnings['unpublished'] = 'This mod is unpublished. Users will be unable to view this mod until it is published.';
        }

        // Check if the mod is disabled
        if ($this->mod->disabled) {
            $warnings['disabled'] = 'This mod is disabled. Users will be unable to view this mod until it is enabled.';
        }

        return $warnings;
    }

    /**
     * Get the total version count visible to the current user.
     */
    public function getVersionCount(): int
    {
        return $this->mod
            ->versions()
            ->when(
                !auth()
                    ->user()
                    ?->can('viewAny', [ModVersion::class, $this->mod]),
                function (Builder $query): void {
                    $query->publiclyVisible();
                },
            )
            ->count();
    }

    /**
     * Get the total comment count visible to the current user.
     */
    public function getCommentCount(): int
    {
        $user = auth()->user();

        return $this->mod->comments()->visibleToUser($user)->count();
    }

    /**
     * Get the total addon count visible to the current user.
     */
    public function getAddonCount(): int
    {
        $user = auth()->user();

        return $this->mod
            ->addons()
            ->when(!$user?->isModOrAdmin(), function (Builder $query): void {
                $query->where('disabled', false)->whereNotNull('published_at')->where('published_at', '<=', now());
            })
            ->whereNull('detached_at')
            ->count();
    }

    /**
     * Check if the mod should display a profile binding notice.
     */
    public function requiresProfileBindingNotice(): bool
    {
        // If the notice is explicitly disabled, don't show it
        if ($this->mod->profile_binding_notice_disabled) {
            return false;
        }

        // Otherwise, check if the category shows profile binding notice
        return $this->mod->category && $this->mod->category->shows_profile_binding_notice;
    }

    /**
     * Check if the mod should display the cheat notice.
     */
    public function requiresCheatNotice(): bool
    {
        return $this->mod->cheat_notice;
    }

    /**
     * Get the mod by ID.
     */
    protected function getMod(int $modId): Mod
    {
        return Mod::query()
            ->with(['sourceCodeLinks', 'category', 'owner', 'additionalAuthors', 'license', 'latestVersion.latestSptVersion', 'latestVersion.latestDependenciesResolved.mod:id,name,slug,thumbnail,thumbnail_hash,owner_id', 'latestVersion.latestDependenciesResolved.mod.owner.role'])
            ->findOrFail($modId);
    }

    /**
     * Redirect to the canonical slug route if the given slug is incorrect.
     */
    protected function enforceCanonicalSlug(Mod $mod, string $slug): void
    {
        if ($mod->slug !== $slug) {
            $this->redirectRoute('mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ]);
        }
    }

    /**
     * Check if a mod has versions which are publicly visible versions. This determines if the mod should show warnings
     * to privileged users about regular user visibility.
     */
    private function hasPublicVersions(): bool
    {
        // Use the scope to check for publicly visible versions
        return $this->mod->versions()->publiclyVisible()->exists();
    }

    /**
     * Get view data.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'mod' => $this->mod,
            'shouldShowWarnings' => $this->shouldShowWarnings(),
            'warningMessages' => $this->getWarningMessages(),
            'requiresProfileBindingNotice' => $this->requiresProfileBindingNotice(),
            'requiresCheatNotice' => $this->requiresCheatNotice(),
            'versionCount' => $this->getVersionCount(),
            'commentCount' => $this->getCommentCount(),
            'addonCount' => $this->getAddonCount(),
            'fikaStatus' => $this->mod->getOverallFikaCompatibility(),
        ];
    }
};
?>

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
                        <p class="mt-2 flex flex-wrap gap-2 items-center">
                            @if ($mod->latestVersion?->latestSptVersion)
                                <span
                                    class="badge-version {{ $mod->latestVersion->latestSptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap"
                                >
                                    {{ $mod->latestVersion->latestSptVersion->version_formatted }}
                                    {{ __('Compatible') }}
                                </span>
                            @else
                                <span
                                    class="badge-version bg-gray-200 text-gray-700 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap"
                                >
                                    {{ __('Unknown SPT Version') }}
                                </span>
                            @endif
                        </p>
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

            {{-- Mobile Cheat Notice Warning --}}
            @if ($requiresCheatNotice)
                <div
                    class="lg:hidden p-3 sm:p-4 bg-red-600 dark:bg-red-700 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                    <div class="flex gap-3 items-start">
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
                                class="underline text-white hover:text-red-200"
                            >Content Guidelines</a> for more information.
                        </div>
                    </div>
                </div>
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
                    <div x-show="selectedTab === 'comments'">
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

            {{-- Required Dependencies --}}
            @if ($mod->latestVersion?->latestDependenciesResolved->isNotEmpty())
                @php
                    $dependencyCount = $mod->latestVersion->latestDependenciesResolved->count();
                @endphp
                <div
                    class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $dependencyCount === 1 ? __('Required Dependency') : __('Required Dependencies') }}
                    </h2>
                    <p class="mt-2 mb-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ $dependencyCount === 1
                            ? __('The latest version of this mod requires the following mod to be installed as well.')
                            : __('The latest version of this mod requires the following mods to be installed as well.') }}
                    </p>
                    <ul
                        role="list"
                        class="divide-y divide-gray-200 dark:divide-gray-800"
                    >
                        @foreach ($mod->latestVersion->latestDependenciesResolved as $dependency)
                            <li class="py-3 first:pt-0 last:pb-0">
                                <a
                                    href="{{ route('mod.show', [$dependency->mod->id, $dependency->mod->slug]) }}"
                                    wire:navigate
                                    class="flex items-center gap-3 group"
                                >
                                    {{-- Mod Thumbnail --}}
                                    @if ($dependency->mod->thumbnail)
                                        <img
                                            src="{{ $dependency->mod->thumbnailUrl }}"
                                            alt="{{ $dependency->mod->name }}"
                                            class="w-12 h-12 rounded-lg flex-shrink-0 object-cover"
                                        >
                                    @else
                                        <div
                                            class="w-12 h-12 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <flux:icon.cube-transparent
                                                class="w-6 h-6 text-gray-400 dark:text-gray-600"
                                            />
                                        </div>
                                    @endif

                                    {{-- Mod Info --}}
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate group-hover:text-cyan-600 dark:group-hover:text-cyan-400">
                                            {{ $dependency->mod->name }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
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

            {{-- Desktop Cheat Notice Warning --}}
            @if ($requiresCheatNotice)
                <div
                    class="hidden lg:block p-3 sm:p-4 bg-red-600 dark:bg-red-700 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                    <div class="flex gap-3 items-start">
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
                                class="underline text-white hover:text-red-200"
                            >Content Guidelines</a> for more information.
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
                    @if ($mod->additionalAuthors->isNotEmpty())
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Additional Authors') }}</h3>
                            <p class="truncate [&>span:not(:last-child)]:after:content-[',_']">
                                @foreach ($mod->additionalAuthors->sortDesc() as $user)
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
                    @if ($mod->latestVersion?->virusTotalLinks->isNotEmpty())
                        <li class="px-4 py-4 last:pb-0 sm:px-0">
                            <h3 class="font-bold">{{ __('Latest Version VirusTotal Results') }}</h3>
                            @foreach ($mod->latestVersion->virusTotalLinks as $virusTotalLink)
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
                    <li class="px-4 py-4 last:pb-0 sm:px-0 flex flex-row gap-2 items-center">
                        <flux:icon
                            icon="{{ $fikaStatus->icon() }}"
                            variant="micro"
                            class="grow-0 size-4 {{ $fikaStatus->colorClass() }}"
                        />
                        <h3 class="grow text-gray-900 dark:text-gray-100">
                            {{ $fikaStatus->modLabel() }}
                        </h3>
                    </li>
                    @if ($mod->contains_ads)
                        <li class="px-4 py-4 last:pb-0 sm:px-0 flex flex-row gap-2 items-center">
                            <flux:icon.check-circle
                                variant="micro"
                                class="grow-0 size-4 text-green-600 dark:text-green-500"
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
                                class="grow-0 size-4 text-green-600 dark:text-green-500"
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
