<x-slot:title>
    {{ __(':title - Mod List - The Forge', ['title' => $modList->title]) }}
</x-slot>

<x-slot:description>
    {{ __(':title: a curated mod list by :owner on The Forge.', ['title' => $modList->title, 'owner' => $modList->owner?->name ?? __('Unknown')]) }}
</x-slot>

<x-slot:header>
    <div class="flex w-full items-center justify-between">
        <div class="flex items-center gap-2 text-xl font-semibold leading-tight text-gray-200">
            @if ($modList->isFavourites())
                <flux:icon.heart class="h-5 w-5 text-rose-500" />
                {{ __('Favourites') }}
            @else
                <flux:icon.list-bullet class="h-5 w-5" />
                {{ __('Mod List') }}
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if ($canManage)
                @if ($modList->visibility === \App\Enums\ListVisibility::Hidden)
                    <flux:modal.trigger name="list-share-link-{{ $modList->id }}">
                        <flux:button
                            icon="link"
                            variant="outline"
                            size="sm"
                        >
                            {{ __('Share link') }}
                        </flux:button>
                    </flux:modal.trigger>
                @endif
                @if ($missingDependencies->isNotEmpty())
                    <flux:modal.trigger name="list-missing-dependencies-{{ $modList->id }}">
                        <flux:button
                            icon="puzzle-piece"
                            variant="outline"
                            size="sm"
                        >
                            {{ __('Add missing dependencies') }}
                        </flux:button>
                    </flux:modal.trigger>
                @endif
                <flux:button
                    icon="pencil"
                    variant="outline"
                    size="sm"
                    :href="route('list.edit', ['listId' => $modList->id])"
                    wire:navigate
                >
                    {{ __('Edit') }}
                </flux:button>
            @endif
            <livewire:list-fork
                :source-id="$modList->id"
                :wire:key="'list-fork-trigger-'.$modList->id"
            />
            <livewire:report-component
                variant="link"
                :reportable-id="$modList->id"
                :reportable-type="\App\Models\ModList::class"
            />
        </div>
    </div>
</x-slot>

<div class="mx-auto max-w-7xl space-y-6 py-6 sm:px-6 lg:px-8">
    <div
        aria-live="polite"
        class="sr-only"
    >{{ $statusMessage }}</div>

    @if ($modList->disabled && ($canManage || auth()->user()?->isModOrAdmin()))
        <flux:callout
            icon="exclamation-triangle"
            color="red"
            inline
        >
            <flux:callout.text>
                {{ __('This list has been disabled by the moderation team and is hidden from everyone else. As :role, you can still view it.', ['role' => auth()->user()?->isModOrAdmin() ? 'a staff member or moderator' : 'the list owner']) }}
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- List info --}}
    <div class="rounded-xl bg-gray-950 p-4 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
        <div class="flex items-start gap-4">
            @if ($modList->isFavourites())
                <div class="flex size-16 shrink-0 items-center justify-center rounded-lg bg-rose-950/30">
                    <flux:icon.heart class="size-8 text-rose-500" />
                </div>
            @elseif ($modList->thumbnail)
                <img
                    src="{{ $modList->thumbnailUrl }}"
                    alt=""
                    class="size-16 shrink-0 rounded-lg object-cover"
                >
            @else
                <div class="flex size-16 shrink-0 items-center justify-center rounded-lg bg-gray-800">
                    <flux:icon.list-bullet class="size-8 text-gray-400" />
                </div>
            @endif

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-100">
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
                            class="badge-version {{ $modList->sptVersion->color_class }} inline-flex items-center whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium"
                        >
                            {{ __('SPT') }} {{ $modList->sptVersion->version }}
                        </span>
                    @endif
                    @if (($modList->public_forks_count ?? 0) > 0)
                        <flux:badge
                            size="sm"
                            icon="share"
                            color="sky"
                        >
                            {{ trans_choice('Forked :count time|Forked :count times', $modList->public_forks_count, [
                                'count' => $modList->public_forks_count,
                            ]) }}
                        </flux:badge>
                    @endif
                </div>
                <p class="mt-1 text-sm text-gray-400">
                    {{ __('by') }}
                    <a
                        href="{{ $modList->owner?->profile_url }}"
                        wire:navigate
                        class="font-medium hover:underline"
                    >
                        {{ $modList->owner?->name ?? __('Unknown') }}
                    </a>
                    <span aria-hidden="true">·</span>
                    <x-time :datetime="$modList->updated_at" />
                    @if ($itemCounts['mods'] > 0)
                        <span aria-hidden="true">·</span>
                        {{ trans_choice(':count mod|:count mods', $itemCounts['mods'], ['count' => $itemCounts['mods']]) }}
                    @endif
                    @if ($itemCounts['addons'] > 0)
                        <span aria-hidden="true">·</span>
                        {{ trans_choice(':count addon|:count addons', $itemCounts['addons'], ['count' => $itemCounts['addons']]) }}
                    @endif
                </p>
                @if ($modList->forked_from_list_id)
                    <p class="mt-1 flex items-center gap-1.5 text-xs text-gray-400">
                        <flux:icon.share
                            variant="micro"
                            class="size-3.5"
                        />
                        @if ($forkedFromSource && $forkedFromViewable)
                            {{ __('Forked from') }}
                            <a
                                href="{{ $forkedFromSource->detailUrl() }}"
                                wire:navigate
                                class="font-medium hover:underline"
                            >{{ $forkedFromSource->title }}</a>
                            {{ __('by') }}
                            <a
                                href="{{ $forkedFromSource->owner?->profile_url }}"
                                wire:navigate
                                class="font-medium hover:underline"
                            >{{ $forkedFromSource->owner?->name ?? __('Unknown') }}</a>
                        @elseif ($forkedFromSource)
                            {{ __('Forked from a list by') }}
                            <span class="font-medium">{{ $forkedFromSource->owner?->name ?? __('Unknown') }}</span>
                        @else
                            {{ __('Forked from a list that has since been removed.') }}
                        @endif
                    </p>
                @endif
                @if ($modList->description_html)
                    <div class="user-markdown mt-3 text-sm">
                        {!! $modList->description_html !!}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($hasIncompatibleMods && $modList->sptVersion)
        <flux:callout
            icon="exclamation-triangle"
            color="amber"
            inline
        >
            <flux:callout.text>
                {{ __('Some mods on this list have no version compatible with SPT :version. The closest available version is shown for those mods.', ['version' => $modList->sptVersion->version]) }}
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Items --}}
    @if ($grouped->isEmpty())
        <div class="rounded-xl bg-gray-950 p-8 text-center shadow-md shadow-gray-950 drop-shadow-2xl">
            @if ($modList->isFavourites())
                <flux:icon.heart class="mx-auto size-12 text-rose-400" />
                <h2 class="mt-2 text-sm font-semibold text-gray-100">
                    {{ __('No favourites yet') }}
                </h2>
                <p class="mt-1 text-sm text-gray-400">
                    @if ($canManage)
                        {{ __('Click the heart icon on any mod page to save it here.') }}
                    @else
                        {{ __('This user has not favourited any mods yet.') }}
                    @endif
                </p>
            @else
                <flux:icon.list-bullet class="mx-auto size-12 text-gray-400" />
                <h2 class="mt-2 text-sm font-semibold text-gray-100">
                    {{ __('This list is empty') }}
                </h2>
                <p class="mt-1 text-sm text-gray-400">
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
            @if ($canManage) wire:sort="reorder" @endif
            class="grid grid-cols-1 items-stretch gap-4 lg:grid-cols-2"
        >
            @foreach ($grouped as $group)
                <div
                    wire:key="list-group-{{ $group['group_key'] }}"
                    @if ($group['is_sortable']) wire:sort:item="{{ $group['mod']->id }}" @endif
                    class="overflow-hidden rounded-xl bg-gray-950 shadow-md shadow-gray-950 drop-shadow-2xl"
                >
                    @if ($group['mod_item']?->isTombstone())
                        @php
                            $tombstoneTitle = $group['tombstone_names_visible']
                                ? $group['mod_item']->tombstoned_name ?? __('Removed mod')
                                : __('Removed item');
                        @endphp
                        <div class="flex items-start gap-3 p-3 text-gray-400 sm:p-4">
                            <div
                                class="flex size-14 shrink-0 items-center justify-center rounded-md bg-gray-900/40 sm:size-16">
                                <flux:icon.no-symbol class="size-7 text-gray-700" />
                            </div>
                            <div class="min-w-0 flex-1 self-center">
                                <div class="truncate text-sm font-medium text-gray-400">
                                    {{ $tombstoneTitle }}
                                </div>
                                <div class="text-xs italic text-gray-500">
                                    {{ __('The author has opted out of mod lists.') }}
                                </div>
                            </div>
                            @if ($canManage)
                                <flux:button
                                    icon="x-mark"
                                    variant="subtle"
                                    size="sm"
                                    square
                                    class="shrink-0 self-center"
                                    :aria-label="__('Remove :name from list', ['name' => $tombstoneTitle])"
                                    wire:click="confirmRemoveItem({{ $group['mod_item']->id }})"
                                />
                            @endif
                        </div>
                    @elseif ($group['mod'])
                        <x-mod.list-row
                            :mod="$group['mod']"
                            :version="$group['resolved_version']"
                            :is-dependency="$dependencyModIds->contains($group['mod']->id)"
                            :dependency-versions="$group['resolved_version']?->latestDependenciesResolved"
                            :incompatible="$group['version_incompatible']"
                            :display-spt-version="$group['display_spt_version']"
                            :list-mod-ids="$listModIds"
                        >
                            <x-slot:note>
                                <x-list.item-note
                                    :item-id="$group['mod_item']?->id"
                                    :note="$group['mod_item']?->note"
                                    :can-manage="$canManage"
                                    :editing="$group['mod_item'] !== null &&
                                        $editingNoteItemId === $group['mod_item']->id"
                                />
                            </x-slot:note>
                            @if ($group['is_sortable'])
                                <button
                                    type="button"
                                    wire:sort:handle
                                    aria-label="{{ __('Reorder :name', ['name' => $group['mod']->name]) }}"
                                    class="cursor-grab touch-none text-gray-400 hover:text-gray-200"
                                >
                                    <flux:icon.bars-3 variant="micro" />
                                </button>
                            @endif
                            @if ($canManage && $group['mod_item'])
                                <flux:button
                                    icon="x-mark"
                                    variant="subtle"
                                    size="sm"
                                    square
                                    :aria-label="__('Remove :name from list', ['name' => $group['mod']->name])"
                                    wire:sort:ignore
                                    wire:click="confirmRemoveItem({{ $group['mod_item']->id }})"
                                />
                            @endif
                        </x-mod.list-row>
                    @else
                        <div class="flex items-center gap-3 p-3 text-gray-400 sm:p-4">
                            <div class="min-w-0 flex-1 text-sm italic">
                                {{ __('This mod is no longer available.') }}
                            </div>
                            @if ($canManage && $group['mod_item'])
                                <flux:button
                                    icon="x-mark"
                                    variant="subtle"
                                    size="sm"
                                    square
                                    class="shrink-0"
                                    :aria-label="__('Remove unavailable mod from list')"
                                    wire:click="confirmRemoveItem({{ $group['mod_item']->id }})"
                                />
                            @endif
                        </div>
                    @endif

                    @if ($group['addons']->isNotEmpty())
                        <ul class="-mt-1 space-y-0.5 pb-3 sm:-mt-2 sm:pb-4">
                            @foreach ($group['addons'] as $addonItem)
                                @if ($addonItem->isTombstone())
                                    @php
                                        $addonTombstoneTitle = $group['tombstone_names_visible']
                                            ? $addonItem->tombstoned_name ?? __('Removed addon')
                                            : __('Removed item');
                                    @endphp
                                    <li wire:key="list-addon-{{ $addonItem->id }}">
                                        <div class="flex items-center gap-3 px-3 py-1.5 text-gray-400 sm:px-4">
                                            <div class="flex w-14 shrink-0 items-center justify-end sm:w-16">
                                                <div
                                                    class="flex size-10 items-center justify-center rounded bg-gray-900/40">
                                                    <flux:icon.no-symbol class="size-5 text-gray-700" />
                                                </div>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="truncate text-sm font-medium text-gray-400">
                                                    {{ $addonTombstoneTitle }}
                                                </div>
                                                <div class="truncate text-xs italic text-gray-500">
                                                    {{ __('The author has opted out of mod lists.') }}
                                                </div>
                                            </div>
                                            @if ($canManage)
                                                <flux:button
                                                    icon="x-mark"
                                                    variant="subtle"
                                                    size="sm"
                                                    square
                                                    class="shrink-0"
                                                    :aria-label="__('Remove :name from list', ['name' => $addonTombstoneTitle])"
                                                    wire:click="confirmRemoveItem({{ $addonItem->id }})"
                                                />
                                            @endif
                                        </div>
                                    </li>
                                @elseif ($addonItem->listable)
                                    <li>
                                        <x-addon.list-item
                                            :addon="$addonItem->listable"
                                            :wire-key="'list-addon-' . $addonItem->id"
                                        >
                                            <x-slot:note>
                                                <x-list.item-note
                                                    :item-id="$addonItem->id"
                                                    :note="$addonItem->note"
                                                    :can-manage="$canManage"
                                                    :editing="$editingNoteItemId === $addonItem->id"
                                                    icon-column-class="w-11 sm:w-12"
                                                    margin-top-class="mt-0.5"
                                                />
                                            </x-slot:note>
                                            @if ($canManage)
                                                <flux:button
                                                    icon="x-mark"
                                                    variant="subtle"
                                                    size="sm"
                                                    square
                                                    :aria-label="__('Remove :name from list', ['name' => $addonItem->listable->name])"
                                                    wire:click="confirmRemoveItem({{ $addonItem->id }})"
                                                />
                                            @endif
                                        </x-addon.list-item>
                                    </li>
                                @else
                                    <li wire:key="list-addon-{{ $addonItem->id }}">
                                        <div class="flex items-center gap-3 px-3 py-1.5 text-gray-400 sm:px-4">
                                            <div class="flex w-14 shrink-0 items-center justify-end sm:w-16">
                                                <div
                                                    class="flex size-10 items-center justify-center rounded bg-gray-900/40">
                                                    <flux:icon.no-symbol class="size-5 text-gray-700" />
                                                </div>
                                            </div>
                                            <div class="min-w-0 flex-1 text-sm italic">
                                                {{ __('This addon is no longer available.') }}
                                            </div>
                                            @if ($canManage)
                                                <flux:button
                                                    icon="x-mark"
                                                    variant="subtle"
                                                    size="sm"
                                                    square
                                                    class="shrink-0"
                                                    :aria-label="__('Remove unavailable addon from list')"
                                                    wire:click="confirmRemoveItem({{ $addonItem->id }})"
                                                />
                                            @endif
                                        </div>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>

    @endif

    @if (!$modList->isFavourites() && $modList->visibility !== \App\Enums\ListVisibility::Private)
        <div
            id="comments"
            class="space-y-6"
        >
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

    @if ($canManage)
        <flux:modal
            name="list-remove-item-{{ $modList->id }}"
            class="md:w-[500px]"
        >
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">
                        @if ($pendingRemovalIsAddon)
                            {{ __('Remove addon from list?') }}
                        @else
                            {{ __('Remove mod from list?') }}
                        @endif
                    </flux:heading>
                    <flux:subheading>
                        @if ($pendingRemovalIsAddon)
                            {{ __('":name" will be removed from this list.', ['name' => $pendingRemovalName]) }}
                        @else
                            {{ __('":name" and any of its addons on this list will be removed.', ['name' => $pendingRemovalName]) }}
                        @endif
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <flux:modal.close>
                        <flux:button
                            type="button"
                            variant="ghost"
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>
                    <flux:button
                        type="button"
                        variant="danger"
                        wire:click="removeItem"
                    >
                        {{ __('Remove') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    @if ($canManage && $missingDependencies->isNotEmpty())
        <flux:modal
            name="list-missing-dependencies-{{ $modList->id }}"
            class="md:w-[500px]"
        >
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Add missing dependencies') }}</flux:heading>
                    <flux:subheading>
                        {{ trans_choice(
                            'The following :count mod is required by something on this list but is not yet on it. Add it?|The following :count mods are required by something on this list but are not yet on it. Add them?',
                            $missingDependencies->count(),
                            ['count' => $missingDependencies->count()],
                        ) }}
                    </flux:subheading>
                </div>
                <ul class="max-h-72 divide-y divide-gray-800 overflow-y-auto rounded-md border border-gray-800">
                    @foreach ($missingDependencies as $depMod)
                        <li
                            wire:key="missing-dep-{{ $depMod->id }}"
                            class="flex items-center gap-3 p-2"
                        >
                            <div class="flex size-8 shrink-0 items-center justify-center rounded bg-gray-800">
                                <flux:icon.cube class="size-4 text-gray-500" />
                            </div>
                            <div class="min-w-0 truncate text-sm font-medium">
                                {{ $depMod->name }}
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="flex justify-end gap-3 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button
                        variant="primary"
                        wire:click="addMissingDependencies"
                    >
                        {{ __('Add all') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
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
                <div
                    class="flex items-center gap-2"
                    x-data="{ copied: false }"
                >
                    <flux:input
                        readonly
                        :value="$modList->shareUrl()"
                        onclick="this.select()"
                        class="flex-1"
                        label:sr-only="{{ __('Share link') }}"
                    />
                    <flux:button
                        icon="clipboard-document"
                        variant="outline"
                        :aria-label="__('Copy share link')"
                        @click="navigator.clipboard.writeText(@js($modList->shareUrl())); copied = true; setTimeout(() => copied = false, 2000)"
                    >
                        <span
                            x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                    </flux:button>
                </div>
                <div
                    aria-live="polite"
                    class="sr-only"
                    x-text="copied ? @js(__('Share link copied to clipboard.')) : ''"
                ></div>
                <div class="flex justify-between">
                    <flux:modal.trigger name="list-regenerate-share-{{ $modList->id }}">
                        <flux:button
                            variant="ghost"
                            size="sm"
                        >
                            {{ __('Regenerate link') }}
                        </flux:button>
                    </flux:modal.trigger>
                    <flux:modal.close>
                        <flux:button variant="outline">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>

        <flux:modal
            name="list-regenerate-share-{{ $modList->id }}"
            class="md:w-[500px]"
        >
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Regenerate share link?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('The existing link will stop working. Anyone you have already shared the current link with will need the new one.') }}
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button
                        variant="primary"
                        wire:click="regenerateShareToken"
                    >
                        {{ __('Regenerate link') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
