@props(['comment', 'permissions', 'manager', 'isReply' => false, 'commentable' => null])

<x-comment.card
    :comment="$comment"
    :anchor-id="$manager->getCommentHashId($comment->id)"
>
    <x-slot:headerTrailing>
        @if ($comment->parent_id && $comment->parent)
            <a
                href="#{{ $manager->getCommentHashId($comment->parent_id) }}"
                @class([
                    'ml-2 text-xs text-slate-400 underline [&_span]:underline hover:text-cyan-400',
                    'mr-10' => $permissions->can($comment->id, 'viewActions'),
                ])
            >
                {{ __('Replying to') }} @<x-user-name :user="$comment->parent->user" />
            </a>
        @endif
        @if ($permissions->can($comment->id, 'viewActions'))
            <x-comment.action-menu
                :comment="$comment"
                :permissions="$permissions"
                :descendants-count="$comment->isRoot() ? $manager->getDescendantCount($comment->id) : null"
            />
        @endif
    </x-slot>

    <x-comment.actions
        :comment="$comment"
        :permissions="$permissions"
        :manager="$manager"
        :show-replies-toggle="$comment->isRoot()"
    />

    {{-- Reply Form --}}
    @if (
        $manager->isFormVisible('reply', $comment->id) &&
            CachedGate::allows('create', [App\Models\Comment::class, $comment->commentable, $comment]))
        <div class="mt-4">
            <flux:separator text="Reply To Comment" />
            <div class="mt-2.5">
                <x-comment.form
                    form-key="formStates.reply-{{ $comment->id }}.body"
                    data-test="reply-body-{{ $comment->id }}"
                    submit-action="createReply({{ $comment->id }})"
                    submit-text="{{ __('Post Reply') }}"
                    cancel-action="toggleReplyForm({{ $comment->id }})"
                />
            </div>
        </div>
    @endif

    {{-- Edit Form --}}
    @if ($manager->isFormVisible('edit', $comment->id))
        <div class="mt-4">
            <flux:separator text="Edit Comment" />
            <div class="mt-2.5">
                <x-comment.form
                    form-key="formStates.edit-{{ $comment->id }}.body"
                    data-test="edit-body-{{ $comment->id }}"
                    submit-action="updateComment({{ $comment->id }})"
                    submit-text="{{ __('Update Comment') }}"
                    cancel-action="toggleEditForm({{ $comment->id }})"
                />
            </div>
        </div>
    @endif
</x-comment.card>
