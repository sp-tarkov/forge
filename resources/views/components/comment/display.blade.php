@props(['comment', 'manager', 'isReply' => false])

<div x-data="{
         canEdit: {{ $manager->canEditComment($comment) ? 'true' : 'false' }},
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
    <div id="comment-{{ $comment->id }}" class="flex items-center justify-between">
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
    </div>
    @if ($comment->parent_id && $comment->parent)
        <a href="#comment-{{ $comment->parent_id }}" class="underline hover:text-cyan-400 ml-2 text-xs text-slate-400">
            {{ 'Replying to @' . $comment->parent->user->name }}
        </a>
    @endif
</div>

<div class="text-gray-900 dark:text-slate-200 mt-3">
    {!! Illuminate\Support\Str::markdown($comment->body) !!}
</div>

<div class="flex items-center gap-6 mt-4 text-slate-400">
    @if (auth()->check() && $comment->user_id === auth()->id())
        <flux:tooltip content="You cannot like your own comment" position="right" gap="10">
            <button type="button" class="relative flex items-center gap-1 transition cursor-not-allowed!" disabled>
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" stroke="none" viewBox="0 0 20 20" class="w-5 h-5 relative z-10">
                        <path d="M3.172 5.172a4 4 0 0 1 5.656 0L10 6.343l1.172-1.171a4 4 0 1 1 5.656 5.656L10 17.657l-6.828-6.829a4 4 0 0 1 0-5.656Z"/>
                    </svg>
                </div>
                <span class="text-xs">{{ $comment->reactions->count() }} {{ $comment->reactions->count() === 1 ? 'Like' : 'Likes' }}</span>
            </button>
        </flux:tooltip>
    @else
        <button
            type="button"
            class="relative flex items-center gap-1 transition {{ $manager->hasReacted($comment->id) ? 'text-red-400' : '' }} hover:text-red-400"
            wire:click="toggleReaction({{ $comment->id }})"
            x-on:click="animate"
            x-data="{
                isAnimating: false,
                animate() {
                    this.isAnimating = true;
                    requestAnimationFrame(() => {
                        setTimeout(() => { this.isAnimating = false; }, 800);
                    });
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
            <span class="text-xs">{{ $comment->reactions->count() }} {{ $comment->reactions->count() === 1 ? 'Like' : 'Likes' }}</span>
        </button>
    @endif

    @can('update', $comment)
        <button type="button"
                wire:click="toggleEditForm({{ $comment->id }})"
                x-show="canEdit"
                class="hover:underline cursor-pointer text-xs">
            {{ __('Edit') }}
        </button>
    @endcan

    <button type="button"
            wire:click="toggleReplyForm({{ $comment->id }})"
            class="hover:underline cursor-pointer text-xs">
        {{ __('Reply') }}
    </button>

    @if ($comment->isRoot() && $comment->descendants->count())
        <button type="button"
                wire:click="toggleReplies({{ $comment->id }})"
                class="hover:underline cursor-pointer text-xs">
            {{ ($manager->showReplies[$comment->id] ?? true) ? 'Hide' : 'Show' }} Replies ({{ $comment->descendants->count() }})
        </button>
    @endif
</div>

{{-- Reply Form --}}
@if ($manager->showReplyForm[$comment->id] ?? false)
    <div class="mt-4">
        <flux:separator text="Reply To Comment" />
        <form wire:submit="createReply({{ $comment->id }})" class="mt-2.5">
            <flux:textarea
                name="body"
                wire:model="replyBodies.comment-{{ $comment->id }}"
                resize="vertical"
                placeholder="{{ __('Please ensure your comment does not break the community guidelines.') }}"
            />
            @error('replyBodies.comment-' . $comment->id)
                <div class="text-red-500 text-xs my-1.5">{{ $message }}</div>
            @enderror
            <div class="flex items-center justify-between mt-2">
                <flux:button variant="primary" size="sm" class="text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700" type="submit">
                    {{ __('Post Reply') }}
                </flux:button>
                <flux:button type="button" wire:click="toggleReplyForm({{ $comment->id }})" variant="danger" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
@endif

{{-- Edit Form --}}
@if ($manager->showEditForm[$comment->id] ?? false)
    <div class="mt-4">
        <flux:separator text="Edit Comment" />
        <form wire:submit="updateComment({{ $comment->id }})" class="mt-2.5">
            <flux:textarea
                name="body"
                wire:model="editBodies.comment-{{ $comment->id }}"
                resize="vertical"
                placeholder="{{ __('Please ensure your comment does not break the community guidelines.') }}"
            />
            @error('editBodies.comment-' . $comment->id)
                <div class="text-red-500 text-xs my-1.5">{{ $message }}</div>
            @enderror
            <div class="flex items-center justify-between mt-2">
                <flux:button variant="primary" size="sm" class="text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700" type="submit">
                    {{ __('Update Comment') }}
                </flux:button>
                <flux:button type="button" wire:click="toggleEditForm({{ $comment->id }})" variant="danger" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
@endif
</div>
