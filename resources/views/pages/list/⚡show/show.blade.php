<x-slot:title>
    {{ __(':title - Mod List - The Forge', ['title' => $modList->title]) }}
</x-slot>

<x-slot:description>
    {{ __(':title: a curated mod list by :owner on The Forge.', ['title' => $modList->title, 'owner' => $modList->owner?->name ?? __('Unknown')]) }}
</x-slot>

<x-slot:header></x-slot>

<div class="mx-auto max-w-7xl px-2 sm:px-4 lg:px-8 py-6 space-y-6">
    {{-- Header --}}
    <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            @if ($modList->isFavourites())
                <div class="shrink-0 size-24 rounded-lg bg-rose-50 dark:bg-rose-950/30 flex items-center justify-center">
                    <flux:icon.heart class="size-12 text-rose-500" />
                </div>
            @elseif ($modList->thumbnail)
                <img
                    src="{{ $modList->thumbnailUrl }}"
                    alt="{{ $modList->title }}"
                    class="shrink-0 size-24 rounded-lg object-cover"
                >
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $modList->title }}
                    </h1>
                    <flux:badge
                        size="sm"
                        :icon="$modList->visibility->icon()"
                        :color="$modList->visibility === \App\Enums\ListVisibility::Public ? 'lime' : ($modList->visibility === \App\Enums\ListVisibility::Hidden ? 'amber' : 'zinc')"
                    >
                        {{ __($modList->visibility->label()) }}
                    </flux:badge>
                    @if ($modList->sptVersion)
                        <span
                            class="badge-version {{ $modList->sptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium whitespace-nowrap"
                        >
                            {{ __('SPT') }} {{ $modList->sptVersion->version }}
                        </span>
                    @endif
                    @if ($modList->is_default)
                        <flux:badge
                            size="sm"
                            color="pink"
                            icon="heart"
                        >
                            {{ __('Favourites') }}
                        </flux:badge>
                    @endif
                </div>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('by') }}
                    <a
                        href="{{ $modList->owner?->profile_url }}"
                        wire:navigate
                        class="hover:underline font-medium"
                    >
                        {{ $modList->owner?->name ?? __('Unknown') }}
                    </a>
                    · <x-time :datetime="$modList->updated_at" />
                </p>
                @if ($modList->description_html)
                    <div class="user-markdown mt-4">
                        {!! $modList->description_html !!}
                    </div>
                @endif
            </div>

            @if ($canManage)
                <div class="flex items-center gap-2">
                    @if ($modList->visibility === \App\Enums\ListVisibility::Hidden)
                        <flux:modal.trigger name="list-share-link-{{ $modList->id }}">
                            <flux:button
                                icon="link"
                                variant="outline"
                            >
                                {{ __('Share link') }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endif
                    <flux:button
                        icon="pencil"
                        variant="outline"
                        :href="route('list.edit', ['listId' => $modList->id])"
                        wire:navigate
                    >
                        {{ __('Edit') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </div>

    {{-- Body --}}
    @if ($grouped->isEmpty())
        <div class="p-8 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl text-center">
            @if ($modList->isFavourites())
                <flux:icon.heart class="mx-auto size-12 text-rose-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('No favourites yet') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($canManage)
                        {{ __('Click the heart icon on any mod page to save it here.') }}
                    @else
                        {{ __('This user has not favourited any mods yet.') }}
                    @endif
                </p>
            @else
                <flux:icon.list-bullet class="mx-auto size-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('This list is empty') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($canManage)
                        {{ __('Browse mods and use the "Add to list" action to start curating.') }}
                    @else
                        {{ __('The owner has not added any mods yet.') }}
                    @endif
                </p>
                @if ($canManage)
                    <div class="mt-4">
                        <flux:button
                            variant="primary"
                            :href="route('mods')"
                            wire:navigate
                            icon="squares-2x2"
                        >
                            {{ __('Browse mods') }}
                        </flux:button>
                    </div>
                @endif
            @endif
        </div>
    @else
        <div
            class="space-y-4"
            @if ($canManage) x-data="{ order: @js($grouped->map(fn ($group) => $group['mod']?->id)->filter()->values()->all()) }" @endif
        >
            @foreach ($grouped as $groupKey => $group)
                @php($mod = $group['mod'])
                @php($modItem = $group['mod_item'])
                @php($addons = $group['addons'])

                <div
                    wire:key="list-group-{{ $groupKey }}"
                    class="bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl overflow-hidden"
                >
                    @if ($mod)
                        <div class="relative">
                            <x-mod.card
                                :mod="$mod"
                                :version="$mod->latestVersion"
                                section="list"
                                class="shadow-none drop-shadow-none"
                            />

                            <div class="absolute top-2 right-2 flex items-center gap-1">
                                @if ($modItem && $modItem->added_as_dependency)
                                    <flux:badge
                                        size="sm"
                                        color="zinc"
                                        icon="link"
                                    >
                                        {{ __('Dependency') }}
                                    </flux:badge>
                                @endif

                                @if ($mod->latestVersion && $mod->latestVersion->latestDependenciesResolved->isNotEmpty())
                                    <flux:tooltip>
                                        <flux:badge
                                            size="sm"
                                            color="amber"
                                            icon="link"
                                        >
                                            {{ __('Requires :count', ['count' => $mod->latestVersion->latestDependenciesResolved->count()]) }}
                                        </flux:badge>
                                        <flux:tooltip.content>
                                            <div class="text-xs space-y-1">
                                                @foreach ($mod->latestVersion->latestDependenciesResolved as $depVersion)
                                                    <div>· {{ $depVersion->mod?->name }}</div>
                                                @endforeach
                                            </div>
                                        </flux:tooltip.content>
                                    </flux:tooltip>
                                @endif

                                @if ($canManage && $modItem)
                                    <flux:button
                                        icon="x-mark"
                                        variant="subtle"
                                        size="sm"
                                        square
                                        wire:click="removeItem({{ $modItem->id }})"
                                        wire:confirm="{{ __('Remove this mod (and its addons) from the list?') }}"
                                    />
                                @endif
                            </div>

                            @if ($modItem?->note)
                                <div class="border-t border-gray-200 dark:border-gray-800 px-4 py-2 text-sm italic text-gray-700 dark:text-gray-300">
                                    <flux:icon.chat-bubble-left class="inline size-4 mr-1 text-gray-400" />
                                    {{ $modItem->note }}
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-4 text-sm italic text-gray-500 dark:text-gray-400">
                            {{ __('This mod is no longer available.') }}
                        </div>
                    @endif

                    {{-- Nested addons --}}
                    @if ($addons->isNotEmpty())
                        <div class="border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 px-4 py-3 space-y-2">
                            <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <flux:icon.puzzle-piece class="size-3.5" />
                                {{ __(Str::plural('Addon', $addons->count())) }}
                            </div>
                            @foreach ($addons as $addonItem)
                                @php($addon = $addonItem->listable)
                                @if ($addon)
                                    <x-addon.list-item
                                        :addon="$addon"
                                        :note="$addonItem->note"
                                        :wire-key="'list-addon-'.$addonItem->id"
                                    >
                                        @if ($canManage)
                                            <flux:button
                                                icon="x-mark"
                                                variant="subtle"
                                                size="sm"
                                                square
                                                wire:click="removeItem({{ $addonItem->id }})"
                                                wire:confirm="{{ __('Remove this addon from the list?') }}"
                                            />
                                        @endif
                                    </x-addon.list-item>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if (! $modList->isFavourites() && $modList->visibility !== \App\Enums\ListVisibility::Private)
        <div id="comments">
            @if ($modList->comments_disabled && ($canManage || auth()->user()?->isModOrAdmin()))
                <flux:callout
                    icon="exclamation-triangle"
                    color="orange"
                    inline
                >
                    <flux:callout.text>
                        {{ __('Comments have been disabled for this list and are not visible to normal users. As :role, you can still view and manage existing comments.', ['role' => auth()->user()?->isModOrAdmin() ? 'a staff member or moderator' : 'the list owner']) }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            @if ($modList->canReceiveComments() || $canManage || auth()->user()?->isModOrAdmin())
                <livewire:comment-component
                    wire:key="list-comment-component-{{ $modList->id }}"
                    :commentable="$modList"
                />
            @endif
        </div>
    @endif

    @if ($canManage && $modList->visibility === \App\Enums\ListVisibility::Hidden)
        <flux:modal
            name="list-share-link-{{ $modList->id }}"
            class="md:w-[500px]"
        >
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Share this list') }}</flux:heading>
                    <flux:subheading>{{ __('Anyone with this link can view the list.') }}</flux:subheading>
                </div>
                <flux:input
                    readonly
                    :value="$modList->shareUrl()"
                    onclick="this.select()"
                />
                <div class="flex justify-between">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        wire:click="regenerateShareToken"
                        wire:confirm="{{ __('Regenerate the share link? The existing link will stop working.') }}"
                    >
                        {{ __('Regenerate link') }}
                    </flux:button>
                    <flux:modal.close>
                        <flux:button variant="outline">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
