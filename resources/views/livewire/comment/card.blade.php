<div>
    <div id="comment-{{ $comment->id }}" class="flex items-center justify-between">
        <div class="flex items-center">
            <flux:avatar circle="circle" src="{{ $comment->user->profile_photo_url }}" color="auto" color:seed="{{ $comment->user->id }}" />
            <a href="{{ route('user.show', ['userId' => $comment->user->id, 'slug' => $comment->user->slug]) }}" class="ml-2 font-bold text-gray-900 dark:text-white hover:underline">
                {{ $comment->user->name }}
            </a>
            <span class="ml-2 text-xs text-slate-400 relative top-0.5">
                <x-time :datetime="$comment->created_at" />
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
                class="relative flex items-center gap-1 transition {{ $this->hasReacted ? 'text-red-400' : '' }} {{ auth()->check() && $comment->user_id === auth()->id() ? 'cursor-not-allowed!' : 'hover:text-red-400' }}"
                wire:click="react({{ $comment->id }})"
                x-on:click="animate"
                @if(auth()->check() && $comment->user_id === auth()->id()) disabled @endif
            x-data="{
                        isAnimating: false,
                        animate() {
                            if (this.isAnimating || {{ auth()->check() && $comment->user_id === auth()->id() ? 'true' : 'false' }}) return;
                            this.isAnimating = true;
                            requestAnimationFrame(() => {
                                setTimeout(() => {
                                    this.isAnimating = false;
                                }, 800);
                            });
                        }
                    }"
            >
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

        <span class="hover:underline cursor-pointer text-xs">
            {{ __('Reply') }}
        </span>

        @if ($comment->isRoot() && $comment->descendants->count())
            <span x-on:click="showReplies{{ $comment->id }} = !showReplies{{ $comment->id }}" class="hover:underline cursor-pointer text-xs">
                <span x-text="showReplies{{ $comment->id }} ? 'Hide Replies ({{ $comment->descendants->count() }})' : 'Show Replies ({{ $comment->descendants->count() }})'"></span>
            </span>
        @endif
    </div>
</div>
