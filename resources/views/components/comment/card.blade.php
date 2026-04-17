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
                                class="ml-1 italic text-gray-500 dark:text-gray-400"
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

    <div class="user-markdown text-gray-900 dark:text-slate-200 mt-3">
        @if ($comment->isDeleted())
            @if (auth()->check() && auth()->user()->isModOrAdmin())
                <div>
                    <span class="text-gray-500 dark:text-gray-400 italic">
                        {{ __('Comment was deleted on') }} {{ $comment->deleted_at->format('Y-m-d H:i:s') }}:
                    </span>
                    <div class="deleted">
                        {!! $comment->body_html !!}
                    </div>
                </div>
            @else
                <span class="text-gray-500 dark:text-gray-400 italic">
                    [{{ __('deleted at') }} {{ $comment->deleted_at->format('Y-m-d H:i:s') }}]
                </span>
            @endif
        @else
            {!! $comment->body_html !!}
        @endif
    </div>

    {{ $slot }}
</div>
