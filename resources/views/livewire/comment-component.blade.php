<div>
    @auth
        <div class="p-6 mb-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-white">
                    {{ __('Discussion') }}
                    <span class="font-normal text-slate-400">{{ '(' . $commentCount . ')' ?? '' }}</span>
                </h2>
            </div>
            <div class="flex items-start">
                <div class="mr-3">
                    <flux:avatar src="{{ auth()->user()->profile_photo_url }}" color="auto" color:seed="{{ auth()->user()->id }}" circle="circle"/>
                </div>
                <form wire:submit="createComment" class="flex-1">
                    <flux:textarea
                        name="body"
                        wire:model="newCommentBody"
                        resize="vertical"
                        placeholder="{{ __('Please ensure your comment does not break the community guidelines.') }}"
                    />
                    @error('newCommentBody')
                        <div class="text-red-500 text-xs my-1.5">{{ $message }}</div>
                    @enderror
                    <div class="flex items-center justify-between mt-2">
                        <flux:button variant="primary" size="sm" class="text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700" type="submit">
                            {{ __('Post Comment') }}
                        </flux:button>
                        <div class="text-xs text-slate-400 text-right ml-2">
                            {{ __('Basic Markdown formatting is supported.') }}
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endauth

    @if ($rootComments->count() === 0 && auth()->guest())
        <div class="p-8 mb-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl text-center">
            <div class="mb-6">
                <svg class="mx-auto h-16 w-16 text-gray-400 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                {{ __('No comments yet') }}
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6">
                {{ __('Be the first to share your thoughts! Login or register to join the discussion.') }}
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <flux:button variant="primary" size="sm" class="text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700" href="{{ route('login') }}">
                    {{ __('Login') }}
                </flux:button>
                <flux:button variant="ghost" size="sm" class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800" href="{{ route('register') }}">
                    {{ __('Register') }}
                </flux:button>
            </div>
        </div>
    @elseif ($rootComments->count() === 0 && auth()->check())
        <div class="p-8 mb-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl text-center">
            <div class="mb-6">
                <svg class="mx-auto h-16 w-16 text-gray-400 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                {{ __('No comments yet') }}
            </h3>
            <p class="text-gray-600 dark:text-gray-400">
                {{ __('Be the first to share your thoughts about this ' . $commentable->getCommentableDisplayName() . '!') }}
            </p>
        </div>
    @endif

    @if ($rootComments->hasPages())
        <div class="mb-4">
            {{ $rootComments->links() }}
        </div>
    @endif

    @foreach ($rootComments as $comment)
        <div wire:key="comment-{{ $comment->id }}"
             class="p-6 mb-4 last:mb-0 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none transition-all duration-600">
            
            <x-comment.display :comment="$comment" :manager="$this" />

            @if ($comment->descendants->count() > 0 && ($showReplies[$comment->id] ?? true))
                <div class="mt-4 space-y-4">
                    @foreach ($comment->descendants as $descendant)
                        <div wire:key="comment-{{ $descendant->id }}" 
                             class="p-6 bg-gray-50 dark:bg-gray-900 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none transition-all duration-600">
                            <x-comment.display :comment="$descendant" :manager="$this" :is-reply="true" />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    <div class="mt-4">
        {{ $rootComments->links() }}
    </div>
</div>