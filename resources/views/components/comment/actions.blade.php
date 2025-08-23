@props(['comment', 'manager', 'showRepliesToggle' => false])

<div class="flex items-center gap-6 mt-4 text-slate-400">
    @if (!$comment->isDeleted())
        @auth
            @if ($comment->user_id === auth()->id())
                <flux:tooltip content="You cannot like your own comment" position="right" gap="10">
                    <button type="button" class="relative flex items-center gap-1 transition cursor-not-allowed!" disabled>
                        <div class="relative">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" stroke="none" viewBox="0 0 20 20" class="w-5 h-5 relative z-10">
                                <path d="M3.172 5.172a4 4 0 0 1 5.656 0L10 6.343l1.172-1.171a4 4 0 1 1 5.656 5.656L10 17.657l-6.828-6.829a4 4 0 0 1 0-5.656Z"/>
                            </svg>
                        </div>
                        <span class="text-xs">{{ $comment->reactions_count ?? 0 }} {{ ($comment->reactions_count ?? 0) === 1 ? 'Like' : 'Likes' }}</span>
                    </button>
                </flux:tooltip>
            @else
                <button
                    type="button"
                    class="relative flex items-center gap-1 transition {{ auth()->check() && $comment->reactions->contains('user_id', auth()->id()) ? 'text-red-400' : '' }} hover:text-red-400"
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
                            this.timeoutId = setTimeout(() => { this.isAnimating = false; this.timeoutId = null; }, 800);
                        }
                    }">
                    <div class="relative">
                        <svg x-transition:enter="animate-heart-bounce" x-show="isAnimating" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="currentColor" stroke="none" viewBox="0 0 20 20" class="w-5 h-5 relative z-10">
                            <path d="M3.172 5.172a4 4 0 0 1 5.656 0L10 6.343l1.172-1.171a4 4 0 1 1 5.656 5.656L10 17.657l-6.828-6.829a4 4 0 0 1 0-5.656Z"/>
                        </svg>
                        <svg x-show="!isAnimating" xmlns="http://www.w3.org/2000/svg" fill="currentColor" stroke="none" viewBox="0 0 20 20" class="w-5 h-5 relative z-10">
                            <path d="M3.172 5.172a4 4 0 0 1 5.656 0L10 6.343l1.172-1.171a4 4 0 1 1 5.656 5.656L10 17.657l-6.828-6.829a4 4 0 0 1 0-5.656Z"/>
                        </svg>
                    </div>
                    <span class="text-xs">{{ $comment->reactions_count ?? 0 }} {{ ($comment->reactions_count ?? 0) === 1 ? 'Like' : 'Likes' }}</span>
                </button>
            @endif
        @else
            {{-- Show reaction count to guests but without interaction --}}
            <div class="relative flex items-center gap-1 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" stroke="none" viewBox="0 0 20 20" class="w-5 h-5">
                    <path d="M3.172 5.172a4 4 0 0 1 5.656 0L10 6.343l1.172-1.171a4 4 0 1 1 5.656 5.656L10 17.657l-6.828-6.829a4 4 0 0 1 0-5.656Z"/>
                </svg>
                <span class="text-xs">{{ $comment->reactions_count ?? 0 }} {{ ($comment->reactions_count ?? 0) === 1 ? 'Like' : 'Likes' }}</span>
            </div>
        @endauth

        @if (\App\Support\CachedGate::allows('update', $comment))
            <button type="button"
                    wire:click="toggleEditForm({{ $comment->id }})"
                    data-test="edit-button-{{ $comment->id }}"
                    x-show="canEdit"
                    class="hover:underline cursor-pointer text-xs">
                {{ __('Edit') }}
            </button>
        @endif

        @if (\App\Support\CachedGate::allows('delete', $comment))
            <button type="button"
                    wire:click="confirmDeleteComment({{ $comment->id }})"
                    data-test="delete-button-{{ $comment->id }}"
                    class="hover:underline cursor-pointer text-xs text-red-500 hover:text-red-700">
                {{ __('Remove') }}
            </button>
        @endif

        @if (\App\Support\CachedGate::allows('showOwnerPinAction', $comment))
            <button type="button"
                    wire:click="{{ $comment->isPinned() ? 'unpinComment' : 'pinComment' }}({{ $comment->id }})"
                    class="hover:underline cursor-pointer text-xs text-cyan-500">
                {{ $comment->isPinned() ? __('Unpin') : __('Pin') }}
            </button>
        @endif

        <livewire:report-component
            wire:key="report-{{ $comment->id }}"
            variant="comment"
            :reportable-id="$comment->id"
            :reportable-type="get_class($comment)"
        />

        @auth
            <button type="button" wire:click="toggleReplyForm({{ $comment->id }})" data-test="reply-button-{{ $comment->id }}" class="hover:underline cursor-pointer text-xs">
                {{ __('Reply') }}
            </button>
        @endauth
    @endif

    @if ($showRepliesToggle && $manager->getDescendantCount($comment->id) > 0)
        <button type="button"
                wire:click="toggleDescendants({{ $comment->id }})"
                data-test="toggle-replies-{{ $comment->id }}"
                class="hover:underline cursor-pointer text-xs">
            {{ ($manager->showDescendants[$comment->id] ?? false) ? 'Hide' : 'Show' }} Replies ({{ $manager->getDescendantCount($comment->id) }})
        </button>
    @endif
</div>
