<x-slot:title>
    {{ __(':title - Mod List - The Forge', ['title' => $modList->title]) }}
</x-slot>

<x-slot:description>
    {{ __(':title: a curated mod list by :owner on The Forge.', ['title' => $modList->title, 'owner' => $modList->owner?->name ?? __('Unknown')]) }}
</x-slot>

<x-slot:header>
    <div class="flex items-center justify-between w-full">
        <h2
            class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2"
        >
            @if ($modList->isFavourites())
                <flux:icon.heart class="w-5 h-5 text-rose-500" />
                {{ __('Favourites') }}
            @else
                <flux:icon.list-bullet class="w-5 h-5" />
                {{ __('Mod List') }}
            @endif
        </h2>
        @if ($canManage)
            <div class="flex items-center gap-2">
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
                <flux:button
                    icon="pencil"
                    variant="outline"
                    size="sm"
                    :href="route('list.edit', ['listId' => $modList->id])"
                    wire:navigate
                >
                    {{ __('Edit') }}
                </flux:button>
            </div>
        @endif
    </div>
</x-slot>

<div class="mx-auto max-w-7xl px-2 sm:px-4 lg:px-8 py-6 space-y-6">
    {{-- List info --}}
    <div
        class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl"
    >
        <div class="flex items-start gap-4">
            @if ($modList->isFavourites())
                <div
                    class="shrink-0 size-16 rounded-lg bg-rose-50 dark:bg-rose-950/30 flex items-center justify-center"
                >
                    <flux:icon.heart class="size-8 text-rose-500" />
                </div>
            @elseif ($modList->thumbnail)
                <img
                    src="{{ $modList->thumbnailUrl }}"
                    alt="{{ $modList->title }}"
                    class="shrink-0 size-16 rounded-lg object-cover"
                >
            @else
                <div
                    class="shrink-0 size-16 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center"
                >
                    <flux:icon.list-bullet class="size-8 text-gray-400" />
                </div>
            @endif

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
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
                @if ($modList->description_html)
                    <div class="user-markdown mt-3 text-sm">
                        {!! $modList->description_html !!}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Items --}}
    @if ($grouped->isEmpty())
        <div
            class="p-8 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl text-center"
        >
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
            class="bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl overflow-hidden"
        >
            <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                @foreach ($grouped as $groupKey => $group)
                    @php($mod = $group['mod'])
                    @php($modItem = $group['mod_item'])
                    @php($addons = $group['addons'])

                    <li wire:key="list-group-{{ $groupKey }}">
                        @if ($mod)
                            <x-mod.list-row
                                :mod="$mod"
                                :version="$mod->latestVersion"
                                :note="$modItem?->note"
                                :is-dependency="$dependencyModIds->contains($mod->id)"
                                :dependency-versions="$mod->latestVersion?->latestDependenciesResolved"
                                :list-mod-ids="$listModIds"
                            >
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
                            </x-mod.list-row>
                        @else
                            <div class="p-3 sm:p-4 text-sm italic text-gray-500 dark:text-gray-400">
                                {{ __('This mod is no longer available.') }}
                            </div>
                        @endif

                        @if ($addons->isNotEmpty())
                            <ul
                                class="pl-6 sm:pl-8 pr-3 sm:pr-4 pb-3 sm:pb-4 space-y-1 ml-6 sm:ml-8 border-l-2 border-gray-100 dark:border-gray-900"
                            >
                                @foreach ($addons as $addonItem)
                                    @php($addon = $addonItem->listable)
                                    @if ($addon)
                                        <li>
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
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
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
