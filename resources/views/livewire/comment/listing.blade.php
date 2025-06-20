<div>
    @auth
        <livewire:comment.form
            wire:key="comment-form-{{ $commentable->id }}"
            :commentable="$commentable"
        />
    @endauth

    @foreach ($rootComments as $comment)
        <div
            wire:key="comment-card-{{ $comment->id }}"
            class="p-6 mb-4 last:mb-0 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none transition-all duration-600"
            {!! ($comment->descendants->count()) ? 'x-data="{ showReplies'.$comment->id.' : $persist(true).as(\'CommentShowReplies'.$comment->id.'\') }"' : '' !!}
        >
            <livewire:comment.card wire:key="comment-card-{{ $comment->id }}" :comment="$comment" />

            @if ($comment->descendants->count() > 0)
                <div class="transition-all duration-600 mt-4" x-show="showReplies{{ $comment->id }}" x-collapse>
                    @foreach ($comment->descendants as $descendant)
                        <div class="p-6 mb-4 last:mb-0 bg-gray-50 dark:bg-gray-900 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none transition-all duration-600">
                            <livewire:comment.card wire:key="comment-card-{{ $descendant->id }}" :comment="$descendant" />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
