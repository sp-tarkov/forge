@props(['comment', 'permissions', 'manager', 'isReply' => false, 'commentable' => null])

@if ($manager->isCommentAuthorBlocked($comment))
    <div
        x-data="{ showBlockedComment: false }"
        data-test="blocked-comment-{{ $comment->id }}"
    >
        <div
            x-show="! showBlockedComment"
            class="flex items-center justify-between gap-3"
        >
            <span class="text-sm italic text-gray-400">
                {{ __('This comment is from a user you have blocked.') }}
            </span>
            <button
                type="button"
                x-on:click="showBlockedComment = true"
                data-test="show-blocked-comment-{{ $comment->id }}"
                class="cursor-pointer whitespace-nowrap text-xs text-cyan-500 hover:underline"
            >
                {{ __('Show comment') }}
            </button>
        </div>
        <div
            x-show="showBlockedComment"
            x-cloak
        >
            <x-comment.display-content
                :comment="$comment"
                :permissions="$permissions"
                :manager="$manager"
                :is-reply="$isReply"
                :commentable="$commentable"
            />
        </div>
    </div>
@else
    <x-comment.display-content
        :comment="$comment"
        :permissions="$permissions"
        :manager="$manager"
        :is-reply="$isReply"
        :commentable="$commentable"
    />
@endif
