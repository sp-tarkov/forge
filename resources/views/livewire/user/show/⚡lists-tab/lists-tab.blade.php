@placeholder
    <div
        id="lists"
        class="space-y-4"
    >
        <flux:skeleton class="h-10 w-64 rounded" />
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @for ($i = 0; $i < 3; $i++)
                <div class="p-4 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                    <flux:skeleton.group class="space-y-3">
                        <flux:skeleton class="h-5 w-3/4 rounded" />
                        <flux:skeleton class="h-4 w-1/2 rounded" />
                        <flux:skeleton class="h-4 w-full rounded" />
                    </flux:skeleton.group>
                </div>
            @endfor
        </div>
    </div>
@endplaceholder

<div id="lists">
    @if ($this->isOwner)
        <div class="mb-4 flex items-center justify-between">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                {{ __(':count of :max lists', ['count' => $this->listCount, 'max' => config('mod-lists.max_lists_per_user')]) }}
            </div>
            <flux:button
                variant="primary"
                size="sm"
                icon="plus"
                :href="route('list.create')"
                wire:navigate
            >
                {{ __('New list') }}
            </flux:button>
        </div>
    @endif

    @if ($this->lists->total() > 0)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->lists as $list)
                <a
                    wire:key="user-lists-card-{{ $list->id }}"
                    href="{{ $list->detailUrl() }}"
                    wire:navigate
                    class="flex flex-col p-4 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl hover:bg-gray-50 dark:hover:bg-black"
                >
                    <div class="flex items-start gap-3">
                        @if ($list->isFavourites())
                            <div class="shrink-0 size-12 rounded-lg bg-rose-50 dark:bg-rose-950/30 flex items-center justify-center">
                                <flux:icon.heart class="size-6 text-rose-500" />
                            </div>
                        @elseif ($list->thumbnail)
                            <img
                                src="{{ $list->thumbnailUrl }}"
                                alt="{{ $list->title }}"
                                class="shrink-0 size-12 rounded-lg object-cover"
                            >
                        @else
                            <div class="shrink-0 size-12 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                <flux:icon.list-bullet class="size-6 text-gray-400" />
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 justify-between">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    {{ $list->title }}
                                </h3>
                                <flux:icon
                                    :name="$list->visibility->icon()"
                                    class="size-4 text-gray-500 shrink-0"
                                />
                            </div>
                        </div>
                    </div>
                    @if ($list->sptVersion)
                        <div class="mt-2">
                            <span class="badge-version {{ $list->sptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium">
                                {{ $list->sptVersion->version }}
                            </span>
                        </div>
                    @endif
                    <div class="mt-auto pt-3 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <flux:icon.list-bullet class="size-3.5" />
                        <span>{{ $list->items_count }} {{ __(Str::plural('item', $list->items_count)) }}</span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">
            {{ $this->lists->links() }}
        </div>
    @else
        <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <div class="text-center py-8">
                <flux:icon.list-bullet class="mx-auto size-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('No lists yet') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($this->isOwner)
                        {{ __('Create a list to start organizing your favourite mods.') }}
                    @else
                        {{ __('This user has not created any public lists yet.') }}
                    @endif
                </p>
            </div>
        </div>
    @endif
</div>
