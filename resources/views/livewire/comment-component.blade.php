<div
    x-data="{
        init() {
            // Check for deep linked comment on page load
            const hash = window.location.hash;
            if (!hash || !hash.includes('comment-')) {
                return;
            }
            const commentId = hash.match(/comment-(\d+)/)?.[1];
            if (commentId && $wire?.handleDeepLink) {
                $wire.handleDeepLink(parseInt(commentId, 10));
                console.log('init');
            }
        }
    }"
    @scroll-to-comment.window="
        const { commentId } = $event.detail;
        const elementId = `reply-container-${commentId}`;
        requestAnimationFrame(() => {
            const element = document.getElementById(elementId);
            if (!element) return;

            element.scrollIntoView({ behavior: 'smooth', block: 'start' });

            const highlightClasses = ['bg-yellow-100', 'dark:bg-sky-700', 'transition-colors', 'duration-1000'];
            element.classList.add(...highlightClasses);
            setTimeout(() => element.classList.remove(...highlightClasses), 2000);
        });
    "
>
    @if ($visibleRootComments->count() === 0)
        <x-comment.empty-state :is-guest="auth()->guest()" :commentable="$commentable" />
    @endif

    @auth
        @if (CachedGate::allows('create', [App\Models\Comment::class, $commentable]))
            <div class="p-6 mb-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white">
                        {{ __('Discussion') }}
                        <span class="font-normal text-slate-400">{{ '(' . $this->commentCount . ')' ?? '' }}</span>
                    </h2>

                    <flux:button
                        wire:click="toggleSubscription"
                        data-test="subscription-toggle"
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
                            data-test="new-comment-body"
                            submit-action="createComment"
                            submit-text="{{ __('Post Comment') }}"
                        />
                    </div>
                </div>
            </div>
        @else
            <div class="p-6 mb-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                    <flux:icon.chat-bubble-left-ellipsis class="w-12 h-12 mx-auto mb-4 opacity-50" />
                    @if ($commentable->comments_disabled)
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">{{ __('Comments Disabled') }}</h3>
                        <p>{{ __('Comments are disabled.') }}</p>
                    @elseif (!auth()->user()->hasVerifiedEmail())
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">{{ __('Email Verification Required') }}</h3>
                        <p class="mb-4">{{ __('Please verify your email address to participate in discussions.') }}</p>
                        <flux:button href="{{ route('verification.notice') }}" size="sm">{{ __('Verify Email') }}</flux:button>
                    @else
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">{{ __('Cannot Comment') }}</h3>
                        <p>{{ __('You do not have permission to comment on this content.') }}</p>
                    @endif
                </div>
            </div>
        @endif
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
                :can-see-ribbon="CachedGate::allows('seeRibbon', $comment)"
            />
            <div
                wire:key="comment-{{ $comment->id }}"
                class="p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none"
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
                                    :can-see-ribbon="CachedGate::allows('seeRibbon', $reply)"
                                />
                                <div
                                    id="reply-container-{{ $reply->id }}"
                                    wire:key="reply-{{ $reply->id }}"
                                    class="p-6 bg-gray-50 dark:bg-gray-900 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none"
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

    {{-- Remove Comment Modal --}}
    @if($showDeleteModal)
        <flux:modal wire:model.self="showDeleteModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="trash" class="w-8 h-8 text-red-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Remove Comment') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('This action cannot be undone') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ $deleteModalMessage }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button x-on:click="$wire.showDeleteModal = false" data-test="cancel-delete-comment" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="deleteComment" data-test="confirm-delete-comment" variant="primary" size="sm" icon="trash" class="bg-red-600 hover:bg-red-700">
                    {{ __('Remove Comment') }}
                </flux:button>
            </div>
        </div>
        </flux:modal>
    @endif

    {{-- Hide Comment Modal --}}
    @if($showSoftDeleteModal)
        <flux:modal wire:model.self="showSoftDeleteModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="eye-slash" class="w-8 h-8 text-amber-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Soft-delete Comment') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Comment will be hidden but can be restored') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('This comment will be hidden from regular users but can be restored later by moderators.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button x-on:click="$wire.showSoftDeleteModal = false" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="softDeleteComment" variant="primary" size="sm" icon="eye-slash" class="bg-amber-600 hover:bg-amber-700 text-white">
                    {{ __('Soft-delete Comment') }}
                </flux:button>
            </div>
        </div>
        </flux:modal>
    @endif

    {{-- Mod Owner Soft-delete Comment Modal --}}
    @if($showModOwnerSoftDeleteModal)
        <flux:modal wire:model.self="showModOwnerSoftDeleteModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="eye-slash" class="w-8 h-8 text-amber-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Soft-delete Comment') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Hide comment from public view') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('Are you sure you want to soft-delete this comment? The comment will be hidden from public view but can be restored by moderators.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button x-on:click="$wire.showModOwnerSoftDeleteModal = false" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="modOwnerSoftDeleteComment" variant="primary" size="sm" icon="eye-slash" class="bg-amber-600 hover:bg-amber-700 text-white">
                    {{ __('Soft-delete Comment') }}
                </flux:button>
            </div>
        </div>
        </flux:modal>
    @endif

    {{-- Mod Owner Restore Comment Modal --}}
    @if($showModOwnerRestoreModal)
        <flux:modal wire:model.self="showModOwnerRestoreModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="arrow-path" class="w-8 h-8 text-green-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Restore Comment') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Make comment visible again') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('Are you sure you want to restore this comment? The comment will become visible to all users again.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button x-on:click="$wire.showModOwnerRestoreModal = false" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="modOwnerRestoreComment" variant="primary" size="sm" icon="arrow-path" class="bg-green-600 hover:bg-green-700 text-white">
                    {{ __('Restore Comment') }}
                </flux:button>
            </div>
        </div>
        </flux:modal>
    @endif

    {{-- Permanently Remove Comment Modal --}}
    @if($showHardDeleteModal)
        <flux:modal wire:model.self="showHardDeleteModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="trash" class="w-8 h-8 text-red-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Permanently Remove Comment') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('This action is irreversible') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    @if ($hardDeleteDescendantCount > 0)
                        {{ __('You are about to permanently remove this comment. This action cannot be undone.') }}
                    @else
                        {{ __('You are about to permanently remove this comment. This action cannot be undone.') }}
                    @endif
                </flux:text>

                @if ($hardDeleteDescendantCount > 0)
                    <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-700 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <flux:text class="text-amber-900 dark:text-amber-200 text-sm font-medium">
                                    {{ __('Attention!') }}
                                </flux:text>
                                <flux:text class="text-amber-800 dark:text-amber-300 text-sm mt-1">
                                    {{ __('This will also permanently delete :count replies in this comment thread. Consider using soft-delete instead if you want to preserve the replies.', ['count' => $hardDeleteDescendantCount]) }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center text-xs text-red-600 dark:text-red-400">
                    <flux:icon name="shield-exclamation" class="w-4 h-4 mr-2 flex-shrink-0" />
                    <span class="leading-tight">
                        {{ __('Permanent deletion') }}
                    </span>
                </div>

                <div class="flex gap-3">
                    <flux:button x-on:click="$wire.showHardDeleteModal = false" variant="outline" size="sm">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button wire:click="hardDeleteComment" variant="primary" size="sm" icon="trash" class="bg-red-600 hover:bg-red-700 text-white">
                        {{ __('Remove Permanently') }}
                    </flux:button>
                </div>
            </div>
        </div>
        </flux:modal>
    @endif

    {{-- Pin Comment Modal --}}
    @if($showPinModal)
        <flux:modal wire:model.self="showPinModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="bookmark" class="w-8 h-8 text-blue-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Pin Comment') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Highlight important comment') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('Are you sure you want to pin this comment? Pinned comments appear at the top of the comment section.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button x-on:click="$wire.showPinModal = false" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="pinComment" data-test="confirm-pin-comment" variant="primary" size="sm" icon="bookmark" class="bg-blue-600 hover:bg-blue-700 text-white">
                    {{ __('Pin Comment') }}
                </flux:button>
            </div>
        </div>
        </flux:modal>
    @endif

    {{-- Unpin Comment Modal --}}
    @if($showUnpinModal)
        <flux:modal wire:model.self="showUnpinModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="bookmark-slash" class="w-8 h-8 text-amber-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Unpin Comment') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Remove from pinned section') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('Are you sure you want to unpin this comment? It will no longer appear at the top of the comment section.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button x-on:click="$wire.showUnpinModal = false" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="unpinComment" data-test="confirm-unpin-comment" variant="primary" size="sm" icon="bookmark-slash" class="bg-amber-600 hover:bg-amber-700 text-white">
                    {{ __('Unpin Comment') }}
                </flux:button>
            </div>
        </div>
        </flux:modal>
    @endif

    {{-- Mark as Spam Modal --}}
    @if($showMarkAsSpamModal)
        <flux:modal wire:model.self="showMarkAsSpamModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="shield-exclamation" class="w-8 h-8 text-red-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Mark as Spam') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Flag content as spam') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('This comment will be marked as spam and hidden from regular users.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button x-on:click="$wire.showMarkAsSpamModal = false" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="markCommentAsSpam" variant="primary" size="sm" icon="shield-exclamation" class="bg-red-600 hover:bg-red-700 text-white">
                    {{ __('Mark as Spam') }}
                </flux:button>
            </div>
        </div>
        </flux:modal>
    @endif

    {{-- Mark as Clean Modal --}}
    @if($showMarkAsCleanModal)
        <flux:modal wire:model.self="showMarkAsCleanModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="shield-check" class="w-8 h-8 text-green-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Mark as Clean') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Confirm content is not spam') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('Are you sure you want to mark this comment as clean? This will remove any spam flags and make it visible to all users.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button x-on:click="$wire.showMarkAsCleanModal = false" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="markCommentAsHam" variant="primary" size="sm" icon="shield-check" class="bg-green-600 hover:bg-green-700 text-white">
                    {{ __('Mark as Clean') }}
                </flux:button>
            </div>
        </div>
        </flux:modal>
    @endif

    {{-- Check for Spam Modal --}}
    @if($showCheckForSpamModal)
        <flux:modal wire:model.self="showCheckForSpamModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="magnifying-glass" class="w-8 h-8 text-blue-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Check for Spam') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Analyze comment for spam content') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('This will send the comment to the spam detection service for analysis. The system will automatically flag it if spam is detected.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button x-on:click="$wire.showCheckForSpamModal = false" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="checkCommentForSpam" variant="primary" size="sm" icon="magnifying-glass" class="bg-blue-600 hover:bg-blue-700 text-white">
                    {{ __('Check for Spam') }}
                </flux:button>
            </div>
        </div>
        </flux:modal>
    @endif

    {{-- Restore Comment Modal --}}
    @if($showRestoreModal)
        <flux:modal wire:model.self="showRestoreModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="arrow-path" class="w-8 h-8 text-green-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Restore Comment') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Make comment visible again') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('Are you sure you want to restore this comment? It will become visible to users again.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button x-on:click="$wire.showRestoreModal = false" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="restoreComment" variant="primary" size="sm" icon="arrow-path" class="bg-green-600 hover:bg-green-700 text-white">
                    {{ __('Restore Comment') }}
                </flux:button>
            </div>
        </div>
        </flux:modal>
    @endif
</div>
