<div
    x-data="{
        init() {
                // Check for deep linked comment on page load
                const match = window.location.hash.match(/comment-(\d+)/);
                if (!match) return;
    
                // Defer until Livewire has finished its initial commit so $wire is ready.
                this.$nextTick(() => {
                    if (this.$wire?.handleDeepLink) {
                        this.$wire.handleDeepLink(parseInt(match[1], 10));
                    }
                });
            },
            scrollToAnchor(anchorId) {
                if (!anchorId) return;
                const deadline = performance.now() + 3000;
                const attempt = () => {
                    const element = document.getElementById(anchorId);
                    if (element) {
                        element.scrollIntoView({ block: 'start' });
                        const highlightTarget = element.parentElement?.parentElement ?? element;
                        const colorClasses = ['bg-yellow-100', 'bg-sky-700'];
                        const transitionClasses = ['transition-colors', 'duration-1000'];
                        highlightTarget.classList.add(...transitionClasses, ...colorClasses);
                        setTimeout(() => highlightTarget.classList.remove(...colorClasses), 1500);
                        setTimeout(() => highlightTarget.classList.remove(...transitionClasses), 2500);
                        return;
                    }
                    if (performance.now() < deadline) {
                        setTimeout(attempt, 50);
                    }
                };
                setTimeout(attempt, 0);
            }
    }"
    @scroll-to-comment.window="scrollToAnchor($event.detail.anchorId)"
>
    @if ($visibleRootComments->count() === 0 && $commentable->canReceiveComments())
        <x-comment.empty-state
            :is-guest="auth()->guest()"
            :commentable="$commentable"
        />
    @endif

    @auth
        @if (CachedGate::allows('create', [App\Models\Comment::class, $commentable]))
            <div class="mb-6 rounded-xl bg-gray-950 p-6 shadow-md shadow-gray-950 drop-shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
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
            <div class="mb-6 rounded-xl bg-gray-950 p-6 shadow-md shadow-gray-950 drop-shadow-2xl">
                <div class="py-8 text-center text-gray-400">
                    <flux:icon.chat-bubble-left-ellipsis class="mx-auto mb-4 h-12 w-12 opacity-50" />
                    @if (!$commentable->canReceiveComments())
                        <h3 class="mb-2 text-lg font-medium text-gray-100">{{ __('Comments Disabled') }}
                        </h3>
                        <p>{{ __('Comments are disabled.') }}</p>
                    @elseif (!auth()->user()->hasVerifiedEmail())
                        <h3 class="mb-2 text-lg font-medium text-gray-100">
                            {{ __('Email Verification Required') }}</h3>
                        <p class="mb-4">{{ __('Please verify your email address to participate in discussions.') }}</p>
                        <flux:button
                            href="{{ route('verification.notice') }}"
                            size="sm"
                        >{{ __('Verify Email') }}</flux:button>
                    @else
                        <h3 class="mb-2 text-lg font-medium text-gray-100">{{ __('Cannot Comment') }}
                        </h3>
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
            x-data="{
                commentId: {{ $comment->id }},
                isExpanded: true,
                init() {
                    const storageKey = 'comment-expanded-' + this.commentId;
                    const saved = localStorage.getItem(storageKey);
                    if (saved !== null) {
                        this.isExpanded = saved === 'true';
                    }
                },
                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    const storageKey = 'comment-expanded-' + this.commentId;
                    localStorage.setItem(storageKey, this.isExpanded.toString());
                }
            }"
        >
            <livewire:ribbon.comment
                wire:key="ribbon-comment-{{ $comment->id }}"
                :comment-id="$comment->id"
                :spam-status="$comment->spam_status->value"
                :can-see-ribbon="$permissions->can($comment->id, 'seeRibbon')"
            />
            <div
                wire:key="comment-{{ $comment->id }}"
                class="rounded-xl bg-gray-950 p-6 shadow-md shadow-gray-950 drop-shadow-2xl filter-none"
            >
                <x-comment.display
                    :comment="$comment"
                    :permissions="$permissions"
                    :manager="$this"
                    :commentable="$commentable"
                />

                <div
                    x-show="isExpanded"
                    x-collapse
                >
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
                                        :can-see-ribbon="$permissions->can($reply->id, 'seeRibbon')"
                                    />
                                    <div
                                        id="reply-container-{{ $reply->id }}"
                                        wire:key="reply-{{ $reply->id }}"
                                        class="rounded-xl bg-gray-900 p-6 shadow-md shadow-gray-950 drop-shadow-2xl filter-none"
                                    >
                                        <x-comment.display
                                            :comment="$reply"
                                            :permissions="$permissions"
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
        </div>
    @endforeach

    @if ($rootComments->hasPages() && $visibleRootComments->count() > 0)
        <div class="mt-4">
            {{ $rootComments->onEachSide(1)->links(data: ['scrollTo' => '#comments']) }}
        </div>
    @endif

    {{-- Remove Comment Modal --}}
    @if ($showDeleteModal)
        <flux:modal
            wire:model.self="showDeleteModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="trash"
                            class="h-8 w-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Remove Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('This action cannot be undone') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-sm text-gray-300">
                        {{ $deleteModalMessage }}
                    </flux:text>
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                    <flux:button
                        x-on:click="$wire.showDeleteModal = false"
                        data-test="cancel-delete-comment"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="deleteComment"
                        data-test="confirm-delete-comment"
                        variant="primary"
                        size="sm"
                        icon="trash"
                        class="bg-red-600 hover:bg-red-700"
                    >
                        {{ __('Remove Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Hide Comment Modal --}}
    @if ($showSoftDeleteModal)
        <flux:modal
            wire:model.self="showSoftDeleteModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="eye-slash"
                            class="h-8 w-8 text-amber-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Soft-delete Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('Comment will be hidden but can be restored') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-sm text-gray-300">
                        {{ __('This comment will be hidden from regular users but can be restored later by moderators.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                    <flux:button
                        x-on:click="$wire.showSoftDeleteModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="softDeleteComment"
                        variant="primary"
                        size="sm"
                        icon="eye-slash"
                        class="bg-amber-600 text-white hover:bg-amber-700"
                    >
                        {{ __('Soft-delete Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Mod Owner Soft-delete Comment Modal --}}
    @if ($showModOwnerSoftDeleteModal)
        <flux:modal
            wire:model.self="showModOwnerSoftDeleteModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="eye-slash"
                            class="h-8 w-8 text-amber-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Soft-delete Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('Hide comment from public view') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-sm text-gray-300">
                        {{ __('Are you sure you want to soft-delete this comment? The comment will be hidden from public view but can be restored by moderators.') }}
                    </flux:text>
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                    <flux:button
                        x-on:click="$wire.showModOwnerSoftDeleteModal = false"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="modOwnerSoftDeleteComment"
                        variant="primary"
                        size="sm"
                        icon="eye-slash"
                        class="bg-amber-600 text-white hover:bg-amber-700"
                    >
                        {{ __('Soft-delete Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Mod Owner Restore Comment Modal --}}
    @if ($showModOwnerRestoreModal)
        <flux:modal
            wire:model.self="showModOwnerRestoreModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="arrow-path"
                            class="h-8 w-8 text-green-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Restore Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('Make comment visible again') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-sm text-gray-300">
                        {{ __('Are you sure you want to restore this comment? The comment will become visible to all users again.') }}
                    </flux:text>
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                    <flux:button
                        x-on:click="$wire.showModOwnerRestoreModal = false"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="modOwnerRestoreComment"
                        variant="primary"
                        size="sm"
                        icon="arrow-path"
                        class="bg-green-600 text-white hover:bg-green-700"
                    >
                        {{ __('Restore Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Permanently Remove Comment Modal --}}
    @if ($showHardDeleteModal)
        <flux:modal
            wire:model.self="showHardDeleteModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="trash"
                            class="h-8 w-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Permanently Remove Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('This action is irreversible') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-sm text-gray-300">
                        @if ($hardDeleteDescendantCount > 0)
                            {{ __('You are about to permanently remove this comment. This action cannot be undone.') }}
                        @else
                            {{ __('You are about to permanently remove this comment. This action cannot be undone.') }}
                        @endif
                    </flux:text>

                    @if ($hardDeleteDescendantCount > 0)
                        <div class="rounded-lg border border-amber-700 bg-amber-950/30 p-4">
                            <div class="flex items-start gap-3">
                                <flux:icon
                                    name="exclamation-triangle"
                                    class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600"
                                />
                                <div>
                                    <flux:text class="text-sm font-medium text-amber-200">
                                        {{ __('Attention!') }}
                                    </flux:text>
                                    <flux:text class="mt-1 text-sm text-amber-300">
                                        {{ __('This will also permanently delete :count replies in this comment thread. Consider using soft-delete instead if you want to preserve the replies.', ['count' => $hardDeleteDescendantCount]) }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                    @endif

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-between border-t border-gray-700 pt-6">
                    <div class="flex items-center text-xs text-red-400">
                        <flux:icon
                            name="shield-exclamation"
                            class="mr-2 h-4 w-4 flex-shrink-0"
                        />
                        <span class="leading-tight">
                            {{ __('Permanent deletion') }}
                        </span>
                    </div>

                    <div class="flex gap-3">
                        <flux:button
                            x-on:click="$wire.showHardDeleteModal = false; $wire.moderationReason = ''"
                            variant="outline"
                            size="sm"
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button
                            wire:click="hardDeleteComment"
                            variant="primary"
                            size="sm"
                            icon="trash"
                            class="bg-red-600 text-white hover:bg-red-700"
                        >
                            {{ __('Remove Permanently') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Pin Comment Modal --}}
    @if ($showPinModal)
        <flux:modal
            wire:model.self="showPinModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="bookmark"
                            class="h-8 w-8 text-blue-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Pin Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('Highlight important comment') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-sm text-gray-300">
                        {{ __('Are you sure you want to pin this comment? Pinned comments appear at the top of the comment section.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                    <flux:button
                        x-on:click="$wire.showPinModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="pinComment"
                        data-test="confirm-pin-comment"
                        variant="primary"
                        size="sm"
                        icon="bookmark"
                        class="bg-blue-600 text-white hover:bg-blue-700"
                    >
                        {{ __('Pin Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Unpin Comment Modal --}}
    @if ($showUnpinModal)
        <flux:modal
            wire:model.self="showUnpinModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="bookmark-slash"
                            class="h-8 w-8 text-amber-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Unpin Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('Remove from pinned section') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-sm text-gray-300">
                        {{ __('Are you sure you want to unpin this comment? It will no longer appear at the top of the comment section.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                    <flux:button
                        x-on:click="$wire.showUnpinModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="unpinComment"
                        data-test="confirm-unpin-comment"
                        variant="primary"
                        size="sm"
                        icon="bookmark-slash"
                        class="bg-amber-600 text-white hover:bg-amber-700"
                    >
                        {{ __('Unpin Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Mark as Spam Modal --}}
    @if ($showMarkAsSpamModal)
        <flux:modal
            wire:model.self="showMarkAsSpamModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="shield-exclamation"
                            class="h-8 w-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Mark as Spam') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('Flag content as spam') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-sm text-gray-300">
                        {{ __('This comment will be marked as spam and hidden from regular users.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                    <flux:button
                        x-on:click="$wire.showMarkAsSpamModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="markCommentAsSpam"
                        variant="primary"
                        size="sm"
                        icon="shield-exclamation"
                        class="bg-red-600 text-white hover:bg-red-700"
                    >
                        {{ __('Mark as Spam') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Mark as Clean Modal --}}
    @if ($showMarkAsCleanModal)
        <flux:modal
            wire:model.self="showMarkAsCleanModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="shield-check"
                            class="h-8 w-8 text-green-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Mark as Clean') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('Confirm content is not spam') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-sm text-gray-300">
                        {{ __('Are you sure you want to mark this comment as clean? This will remove any spam flags and make it visible to all users.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                    <flux:button
                        x-on:click="$wire.showMarkAsCleanModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="markCommentAsHam"
                        variant="primary"
                        size="sm"
                        icon="shield-check"
                        class="bg-green-600 text-white hover:bg-green-700"
                    >
                        {{ __('Mark as Clean') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Check for Spam Modal --}}
    @if ($showCheckForSpamModal)
        <flux:modal
            wire:model.self="showCheckForSpamModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="magnifying-glass"
                            class="h-8 w-8 text-blue-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Check for Spam') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('Analyze comment for spam content') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-sm text-gray-300">
                        {{ __('This will send the comment to the spam detection service for analysis. The system will automatically flag it if spam is detected.') }}
                    </flux:text>
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                    <flux:button
                        x-on:click="$wire.showCheckForSpamModal = false"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="checkCommentForSpam"
                        variant="primary"
                        size="sm"
                        icon="magnifying-glass"
                        class="bg-blue-600 text-white hover:bg-blue-700"
                    >
                        {{ __('Check for Spam') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Restore Comment Modal --}}
    @if ($showRestoreModal)
        <flux:modal
            wire:model.self="showRestoreModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="arrow-path"
                            class="h-8 w-8 text-green-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Restore Comment') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('Make comment visible again') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-sm text-gray-300">
                        {{ __('Are you sure you want to restore this comment? It will become visible to users again.') }}
                    </flux:text>

                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                    <flux:button
                        x-on:click="$wire.showRestoreModal = false; $wire.moderationReason = ''"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="restoreComment"
                        variant="primary"
                        size="sm"
                        icon="arrow-path"
                        class="bg-green-600 text-white hover:bg-green-700"
                    >
                        {{ __('Restore Comment') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Version History Modal --}}
    <flux:modal
        wire:model="showVersionModal"
        class="max-w-2xl"
    >
        @if ($this->viewingVersion && $this->viewingVersionComment)
            <div class="space-y-4">
                <flux:heading size="lg">
                    {{ __('Version :number', ['number' => $this->viewingVersion->version_number]) }}
                </flux:heading>

                <div class="text-sm text-gray-400">
                    {{ __('By') }} {{ $this->viewingVersionComment->user->name }}
                    {{ __('on') }} {{ $this->viewingVersion->created_at->format('F j, Y \a\t g:i A') }}
                </div>

                <flux:separator />

                <div class="user-markdown text-slate-200">
                    {!! $this->viewingVersion->body_html !!}
                </div>
            </div>
        @endif
    </flux:modal>
</div>
