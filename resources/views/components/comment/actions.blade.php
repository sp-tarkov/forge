@props(['comment', 'permissions', 'manager', 'showRepliesToggle' => false])

<div class="mt-4 flex items-center gap-6 text-slate-400">
    @if (!$comment->isDeleted())
        @verified
            @if ($comment->user_id === auth()->id())
                <flux:tooltip
                    content="You cannot like your own comment"
                    position="right"
                    gap="10"
                >
                    <button
                        type="button"
                        class="cursor-not-allowed! relative flex items-center gap-1 transition"
                        disabled
                    >
                        <div class="relative">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="currentColor"
                                stroke="none"
                                viewBox="0 0 20 20"
                                class="relative z-10 h-5 w-5"
                            >
                                <path
                                    d="M3.172 5.172a4 4 0 0 1 5.656 0L10 6.343l1.172-1.171a4 4 0 1 1 5.656 5.656L10 17.657l-6.828-6.829a4 4 0 0 1 0-5.656Z"
                                />
                            </svg>
                        </div>
                        <span class="text-xs">
                            {{ $comment->reactions_count ?? 0 }}
                            {{ ($comment->reactions_count ?? 0) === 1 ? 'Like' : 'Likes' }}
                        </span>
                    </button>
                </flux:tooltip>
            @else
                <button
                    type="button"
                    class="{{ auth()->check() && $comment->reactions->contains('user_id', auth()->id()) ? 'text-red-400' : '' }} relative flex items-center gap-1 transition hover:text-red-400"
                    wire:click="toggleReaction({{ $comment->id }})"
                    data-test="reaction-button-{{ $comment->id }}"
                    x-on:click="if (!isAnimating) animate()"
                    :disabled="isAnimating"
                    x-data="{
                        isAnimating: false,
                        timeoutId: null,
                        animate() {
                            this.isAnimating = true;
                            if (this.timeoutId) clearTimeout(this.timeoutId);
                            this.timeoutId = setTimeout(() => {
                                this.isAnimating = false;
                                this.timeoutId = null;
                            }, 800);
                        }
                    }"
                >
                    <div class="relative">
                        <svg
                            x-transition:enter="animate-heart-bounce"
                            x-show="isAnimating"
                            style="display: none;"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="currentColor"
                            stroke="none"
                            viewBox="0 0 20 20"
                            class="relative z-10 h-5 w-5"
                        >
                            <path
                                d="M3.172 5.172a4 4 0 0 1 5.656 0L10 6.343l1.172-1.171a4 4 0 1 1 5.656 5.656L10 17.657l-6.828-6.829a4 4 0 0 1 0-5.656Z"
                            />
                        </svg>
                        <svg
                            x-show="!isAnimating"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="currentColor"
                            stroke="none"
                            viewBox="0 0 20 20"
                            class="relative z-10 h-5 w-5"
                        >
                            <path
                                d="M3.172 5.172a4 4 0 0 1 5.656 0L10 6.343l1.172-1.171a4 4 0 1 1 5.656 5.656L10 17.657l-6.828-6.829a4 4 0 0 1 0-5.656Z"
                            />
                        </svg>
                    </div>
                    <span class="text-xs">
                        {{ $comment->reactions_count ?? 0 }}
                        {{ ($comment->reactions_count ?? 0) === 1 ? 'Like' : 'Likes' }}
                    </span>
                </button>
            @endif
        @else
            {{-- Show reaction count to unverified users and guests but without interaction --}}
            <div class="relative flex items-center gap-1 text-gray-400">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                    stroke="none"
                    viewBox="0 0 20 20"
                    class="h-5 w-5"
                >
                    <path
                        d="M3.172 5.172a4 4 0 0 1 5.656 0L10 6.343l1.172-1.171a4 4 0 1 1 5.656 5.656L10 17.657l-6.828-6.829a4 4 0 0 1 0-5.656Z"
                    />
                </svg>
                <span class="text-xs">
                    {{ $comment->reactions_count ?? 0 }}
                    {{ ($comment->reactions_count ?? 0) === 1 ? 'Like' : 'Likes' }}
                </span>
            </div>
        @endverified

        @if ($permissions->can($comment->id, 'update'))
            <button
                type="button"
                wire:click="toggleEditForm({{ $comment->id }})"
                data-test="edit-button-{{ $comment->id }}"
                class="cursor-pointer text-xs hover:underline"
            >
                {{ __('Edit') }}
            </button>
        @endif

        @if ($permissions->can($comment->id, 'delete'))
            <button
                type="button"
                wire:click="confirmDeleteComment({{ $comment->id }})"
                data-test="delete-button-{{ $comment->id }}"
                class="cursor-pointer text-xs text-red-500 hover:text-red-700 hover:underline"
            >
                {{ __('Remove') }}
            </button>
        @endif

        <livewire:report-component
            wire:key="report-{{ $comment->id }}"
            variant="comment"
            :reportable-id="$comment->id"
            :reportable-type="get_class($comment)"
        />

        @verified
            @if (CachedGate::allows('create', [App\Models\Comment::class, $comment->commentable, $comment]))
                <button
                    type="button"
                    wire:click="toggleReplyForm({{ $comment->id }})"
                    data-test="reply-button-{{ $comment->id }}"
                    class="cursor-pointer text-xs hover:underline"
                >
                    {{ __('Reply') }}
                </button>
            @endif
        @endverified
    @endif

    @if ($permissions->can($comment->id, 'showOwnerPinAction'))
        <button
            type="button"
            wire:click="{{ $comment->isPinned() ? 'unpinComment' : 'pinComment' }}({{ $comment->id }})"
            class="cursor-pointer text-xs text-cyan-500 hover:underline"
        >
            {{ $comment->isPinned() ? __('Unpin') : __('Pin') }}
        </button>
    @endif

    @if ($showRepliesToggle && $manager->getDescendantCount($comment->id) > 0)
        <button
            type="button"
            @click="toggleExpanded()"
            data-test="toggle-replies-{{ $comment->id }}"
            class="cursor-pointer text-xs hover:underline"
        >
            <span x-text="isExpanded ? 'Hide' : 'Show'">Hide</span> Replies
            ({{ $manager->getDescendantCount($comment->id) }})
        </button>
    @endif
</div>
