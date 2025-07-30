<div>
    @if ($visibleRootComments->count() === 0)
        <x-comment.empty-state :is-guest="auth()->guest()" :commentable="$commentable" />
    @endif

    @auth
        <div class="p-6 mb-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-white">
                    {{ __('Discussion') }}
                    <span class="font-normal text-slate-400">{{ '(' . $this->commentCount . ')' ?? '' }}</span>
                </h2>

                <flux:button
                    wire:click="toggleSubscription"
                    variant="{{ $isSubscribed ? 'primary' : 'outline' }}"
                    icon="{{ $isSubscribed ? 'bell' : 'bell-alert' }}"
                    size="sm"
                >
                    {{ $isSubscribed ? __('Subscribed') : __('Subscribe') }}
                </flux:button>
            </div>
            <div class="flex items-start">
                <div class="mr-3">
                    <flux:avatar src="{{ auth()->user()->profile_photo_url }}" color="auto" color:seed="{{ auth()->user()->id }}" circle="circle"/>
                </div>
                <div class="flex-1">
                    <x-comment.form
                        form-key="newCommentBody"
                        submit-action="createComment"
                        submit-text="{{ __('Post Comment') }}"
                    />
                </div>
            </div>
        </div>
    @endauth

    @if ($rootComments->hasPages() && $visibleRootComments->count() > 0)
        <div class="mb-4">
            {{ $rootComments->onEachSide(1)->links(data: ['scrollTo' => '#comments']) }}
        </div>
    @endif

    @foreach ($visibleRootComments as $comment)
        <div wire:key="comment-container-{{ $comment->id }}-{{ $comment->updated_at->timestamp }}" class="relative mb-4 last:mb-0">
            <livewire:ribbon.comment
                wire:key="ribbon-comment-{{ $comment->id }}-{{ $comment->updated_at->timestamp }}"
                :comment-id="$comment->id"
                :spam-status="$comment->spam_status->value"
                :can-see-ribbon="auth()->user()?->can('seeRibbon', $comment) ?? false"
            />
            <div wire:key="comment-{{ $comment->id }}-{{ $comment->updated_at->timestamp }}"
                 x-data="{ showReplies: $persist({{ ($showReplies[$comment->id] ?? true) ? 'true' : 'false' }}).as('comment-replies-{{ $comment->id }}') }"
                 class="p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none transition-all duration-600">

                <x-comment.display :comment="$comment" :manager="$this" :commentable="$commentable" />

                @if ($comment->descendants->count() > 0)
                    <div x-show="showReplies"
                         x-collapse
                         class="mt-4 space-y-4">
                        @foreach ($comment->descendants as $descendant)
                            <div wire:key="descendant-container-{{ $descendant->id }}-{{ $descendant->updated_at->timestamp }}" class="relative">
                                <livewire:ribbon.comment
                                    wire:key="ribbon-descendant-{{ $descendant->id }}-{{ $descendant->updated_at->timestamp }}"
                                    :comment-id="$descendant->id"
                                    :spam-status="$descendant->spam_status->value"
                                    :can-see-ribbon="auth()->user()?->can('seeRibbon', $descendant) ?? false"
                                />
                                <div wire:key="descendant-{{ $descendant->id }}-{{ $descendant->updated_at->timestamp }}" class="p-6 bg-gray-50 dark:bg-gray-900 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none transition-all duration-600">
                                    <x-comment.display :comment="$descendant" :manager="$this" :is-reply="true" :commentable="$commentable" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if ($rootComments->hasPages() && $visibleRootComments->count() > 0)
        <div class="mt-4">
            {{ $rootComments->onEachSide(1)->links(data: ['scrollTo' => '#comments']) }}
        </div>
    @endif
</div>
