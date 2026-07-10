@props(['list', 'showOwner' => true])

<div
    {{ $attributes->merge(['class' => 'group relative flex flex-row overflow-hidden bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl hover:bg-black']) }}>
    @if ($list->isFavourites())
        <div
            class="flex w-28 shrink-0 items-center justify-center self-stretch bg-gradient-to-br from-rose-950/40 to-rose-900/20">
            <flux:icon.heart class="size-8 text-rose-500" />
        </div>
    @elseif ($list->thumbnail)
        <img
            src="{{ $list->thumbnailUrl }}"
            @if ($list->thumbnailSrcset) srcset="{{ $list->thumbnailSrcset }}" sizes="7rem" @endif
            alt=""
            width="112"
            height="112"
            loading="lazy"
            decoding="async"
            class="size-28 shrink-0 object-cover"
        >
    @else
        <div class="relative w-28 shrink-0 self-stretch overflow-hidden">
            <div
                class="absolute inset-0 bg-[repeating-linear-gradient(45deg,#020509,#020509_4px,#030712_4px,#030712_8px)] transition-opacity duration-200 group-hover:opacity-0">
            </div>
            <div
                class="absolute inset-0 bg-[repeating-linear-gradient(45deg,#000000,#000000_4px,#010203_4px,#010203_8px)] opacity-0 transition-opacity duration-200 group-hover:opacity-100">
            </div>
            <div class="relative flex h-full items-center justify-center">
                <flux:icon.list-bullet class="size-8 text-gray-600" />
            </div>
        </div>
    @endif

    <div class="flex min-w-0 flex-1 flex-col p-3 sm:p-4">
        <div class="flex items-start justify-between gap-2">
            <h3 class="truncate text-base font-semibold text-gray-100">
                <a
                    href="{{ $list->detailUrl() }}"
                    wire:navigate
                    title="{{ $list->title }}"
                    class="after:absolute after:inset-0 focus-visible:underline focus-visible:decoration-2 focus-visible:outline-none"
                >
                    {{ $list->title }}
                </a>
            </h3>
            @if ($list->sptVersion)
                <span
                    class="badge-version {{ $list->sptVersion->color_class }} inline-flex items-center whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium"
                >
                    <span class="sr-only">{{ __('SPT version') }}&nbsp;</span>{{ $list->sptVersion->version }}
                </span>
            @endif
        </div>

        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-400">
            <span class="inline-flex items-center gap-1">
                <flux:icon
                    :name="$list->visibility->icon()"
                    class="size-3.5"
                />
                {{ __($list->visibility->label()) }}
            </span>
            @if ($showOwner && $list->owner)
                <span aria-hidden="true">&middot;</span>
                <span>{{ __('by :owner', ['owner' => $list->owner->name]) }}</span>
            @endif
        </div>

        @if ($list->description)
            <p class="mt-2 line-clamp-2 text-sm text-gray-300">
                {{ Str::limit(strip_tags($list->description), 200) }}
            </p>
        @endif

        <div class="mt-auto flex items-center gap-2 pt-2 text-xs text-gray-400">
            <flux:icon.list-bullet class="size-4" />
            <span>{{ $list->items_count }} {{ __(Str::plural('item', $list->items_count)) }}</span>
            <span aria-hidden="true">&middot;</span>
            <x-time :datetime="$list->updated_at" />
        </div>
    </div>
</div>
