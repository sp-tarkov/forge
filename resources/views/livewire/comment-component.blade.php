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
                    <flux:avatar
                        src="{{ auth()->user()->profile_photo_url }}"
                        color="auto"
                        color:seed="{{ auth()->user()->id }}"
                        circle="circle"
                    />
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
        <div
            wire:key="comment-container-{{ $comment->id }}"
            class="comment-container-{{ $comment->id }} relative mb-4 last:mb-0"
        >
            <livewire:ribbon.comment
                wire:key="ribbon-comment-{{ $comment->id }}"
                :comment-id="$comment->id"
                :spam-status="$comment->spam_status->value"
                :can-see-ribbon="\App\Support\CachedGate::allows('seeRibbon', $comment)"
            />
            <div
                wire:key="comment-{{ $comment->id }}"
                class="p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none transition-all duration-600"
            >
                <x-comment.display
                    :comment="$comment"
                    :manager="$this"
                    :commentable="$commentable"
                />

                @if (($showDescendants[$comment->id] ?? false) && isset($loadedDescendants[$comment->id]))
                    <div class="mt-4 space-y-4">
                        @foreach ($loadedDescendants[$comment->id] as $reply)
                            <div
                                wire:key="reply-container-{{ $reply->id }}"
                                class="relative"
                            >
                                <livewire:ribbon.comment
                                    wire:key="ribbon-reply-{{ $reply->id }}"
                                    :comment-id="$reply->id"
                                    :spam-status="$reply->spam_status->value"
                                    :can-see-ribbon="\App\Support\CachedGate::allows('seeRibbon', $reply)"
                                />
                                <div
                                    wire:key="reply-{{ $reply->id }}"
                                    class="p-6 bg-gray-50 dark:bg-gray-900 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none transition-all duration-600"
                                >
                                    <x-comment.display
                                        :comment="$reply"
                                        :manager="$this"
                                        :is-reply="true"
                                        :commentable="$commentable"
                                    />
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
