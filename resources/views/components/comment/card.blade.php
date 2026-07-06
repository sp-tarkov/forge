@props(['comment', 'anchorId' => null])

<div class="relative">
    <div
        @if ($anchorId) id="{{ $anchorId }}" @endif
        class="flex items-start sm:items-center justify-between"
    >
        <div class="flex items-start sm:items-center">
            <flux:avatar
                circle="circle"
                src="{{ $comment->user->profile_photo_url }}"
                color="auto"
                color:seed="{{ $comment->user->id }}"
            />
            <div class="ml-2 flex flex-col sm:flex-row sm:items-center">
                <div class="flex items-center">
                    <a
                        href="{{ route('user.show', ['userId' => $comment->user->id, 'slug' => $comment->user->slug]) }}"
                        class="font-bold hover:underline"
                    >
                        <x-user-name :user="$comment->user" />
                    </a>
                    {{-- Pinned badge for small screens (after username) --}}
                    @if ($comment->isPinned())
                        <span class="ml-2 inline-flex sm:hidden items-center gap-1 text-xs text-cyan-500">
                            <flux:icon.bookmark
                                variant="micro"
                                class="size-4"
                            />
                            {{ __('Pinned') }}
                        </span>
                    @endif
                </div>
                <span class="sm:ml-2 text-xs text-slate-400 sm:relative sm:top-0.5">
                    <x-time :datetime="$comment->created_at" />
                    @if ($comment->edited_at)
                        @can('viewVersionHistory', $comment)
                            <x-comment.version-history :comment="$comment" />
                        @else
                            <span
                                class="ml-1 italic text-gray-400"
                                title="{{ $comment->edited_at->format('Y-m-d H:i:s') }}"
                            >{{ __('edited') }}</span>
                        @endcan
                    @endif
                </span>
                {{-- Pinned badge for larger screens (after date) --}}
                @if ($comment->isPinned())
                    <span class="ml-2 hidden sm:inline-flex items-center gap-1 text-xs text-cyan-500 relative top-0.5">
                        <flux:icon.bookmark
                            variant="micro"
                            class="size-4"
                        />
                        {{ __('Pinned') }}
                    </span>
                @endif
            </div>
        </div>
        @isset($headerTrailing)
            <div class="flex items-center">
                {{ $headerTrailing }}
            </div>
        @endisset
    </div>

    <div class="user-markdown text-slate-200 mt-3">
        @if ($comment->isDeleted())
            @if (auth()->check() && auth()->user()->isModOrAdmin())
                <div>
                    <span class="text-gray-400 italic">
                        {{ __('Comment was deleted on') }} {{ $comment->deleted_at->format('Y-m-d H:i:s') }}:
                    </span>
                    <div class="deleted">
                        {!! $comment->body_html !!}
                    </div>
                </div>
            @else
                <span class="text-gray-400 italic">
                    [{{ __('deleted at') }} {{ $comment->deleted_at->format('Y-m-d H:i:s') }}]
                </span>
            @endif
        @else
            {!! $comment->body_html !!}
        @endif
    </div>

    @if (! $comment->isDeleted() && $comment->latestVersion?->isTranslated())
        <div
            class="mt-3 rounded-md border border-gray-700 bg-gray-900/40"
            x-data="{ showTranslation: true }"
        >
            <button
                type="button"
                class="flex w-full items-center gap-1.5 px-3 py-1.5 text-xs text-gray-400 cursor-pointer"
                x-on:click="showTranslation = ! showTranslation"
            >
                <flux:icon.language
                    variant="micro"
                    class="size-4"
                />
                {{ __('Machine translated from :language', ['language' => $comment->latestVersion->detected_language_name ?? __('another language')]) }}
                <flux:icon.chevron-down
                    variant="micro"
                    class="size-4 transition-transform"
                    x-bind:class="{ '-rotate-90': ! showTranslation }"
                />
            </button>
            <div
                class="user-markdown px-3 pb-2 text-sm text-slate-200"
                x-show="showTranslation"
            >
                {!! $comment->latestVersion->translated_body_html !!}
            </div>
        </div>
    @endif

    {{ $slot }}
</div>
