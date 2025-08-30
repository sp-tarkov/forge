@props(['comment', 'manager', 'isReply' => false, 'commentable' => null])

<div class="relative" x-data="{
    canEdit: {{ \App\Support\CachedGate::allows('update', $comment) ? 'true' : 'false' }},
    createdAt: new Date('{{ $comment->created_at->toISOString() }}'),
    init() {
        this.updateCanEdit();
        setInterval(() => { this.updateCanEdit(); }, 30000);
    },
    updateCanEdit() {
        const diffInMinutes = (new Date() - this.createdAt) / (1000 * 60);
        this.canEdit = diffInMinutes <= 5;
    }
}">
    <div id="{{ $manager->getCommentHashId($comment->id) }}" class="flex items-center justify-between">
        <div class="flex items-center">
            <flux:avatar circle="circle" src="{{ $comment->user->profile_photo_url }}" color="auto" color:seed="{{ $comment->user->id }}" />
            <a href="{{ route('user.show', ['userId' => $comment->user->id, 'slug' => $comment->user->slug]) }}"
               class="ml-2 font-bold text-gray-900 dark:text-white hover:underline">
                {{ $comment->user->name }}
            </a>
            <span class="ml-2 text-xs text-slate-400 relative top-0.5">
                <x-time :datetime="$comment->created_at" />
                @if ($comment->edited_at)
                    <span class="text-gray-500 dark:text-gray-400" title="{{ $comment->edited_at->format('Y-m-d H:i:s') }}">*</span>
                @endif
            </span>
            @if ($comment->isPinned())
                <span class="ml-2 inline-flex items-center gap-1 text-xs text-cyan-500 relative top-0.5">
                    <flux:icon.bookmark variant="micro" class="size-4" />
                    {{ __('Pinned') }}
                </span>
            @endif
        </div>
        <div class="flex items-center">
            @if ($comment->parent_id && $comment->parent)
                <a href="#{{ $manager->getCommentHashId($comment->parent_id) }}" class="underline hover:text-cyan-400 ml-2 text-xs text-slate-400">
                    {{ 'Replying to @' . $comment->parent->user->name }}
                </a>
            @endif
            @if (\App\Support\CachedGate::allows('viewActions', $comment))
                <x-comment.action-menu 
                    :comment="$comment"
                    :descendants-count="$comment->isRoot() ? $manager->getDescendantCount($comment->id) : null"
                />
            @endif
        </div>
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

    <x-comment.actions
        :comment="$comment"
        :manager="$manager"
        :show-replies-toggle="$comment->isRoot()"
    />

    {{-- Reply Form --}}
    @if ($manager->isFormVisible('reply', $comment->id) && \App\Support\CachedGate::allows('create', [App\Models\Comment::class, $comment->commentable]))
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
</div>
