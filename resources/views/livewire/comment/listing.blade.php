<div>
    @auth
        <div class="p-6 mb-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-white">
                    {{ __('Discussion') }}
                    <span class="font-normal text-slate-400">{{ '('.$this->commentCount.')' ?? '' }}</span>
                </h2>
                {{--
                    TODO: Add subscribe button to get notified when a new comment is posted.
                    <flux:button size="sm">{{ __('Subscribe') }}</flux:button>
                --}}
            </div>
            <div class="flex items-start">
                <div class="mr-3">
                    <flux:avatar src="{{ auth()->user()->profile_photo_url }}" color="auto" color:seed="{{ auth()->user()->id }}" circle="circle"/>
                </div>
                <form wire:submit="create" class="flex-1">
                    <flux:textarea
                        name="body"
                        wire:model="form.body"
                        resize="vertical"
                        placeholder="{{ __('Please ensure your comment does not break the community guidelines.') }}"
                    />
                    @error('form.body')
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

    @if ($rootComments->hasPages())
        <div class="mb-4">
            {{ $rootComments->links() }}
        </div>
    @endif

    @foreach ($rootComments as $comment)
        <div
            wire:key="comment-container-{{ $comment->id }}"
            class="p-6 mb-4 last:mb-0 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none transition-all duration-600"
            {!! ($comment->descendants->count()) ? 'x-data="{ showReplies'.$comment->id.' : $persist(true).as(\'CommentShowReplies'.$comment->id.'\') }"' : '' !!}
        >
            <livewire:comment.card wire:key="comment-card-{{ $comment->id }}" :comment="$comment" />

            @if ($comment->descendants->count() > 0)
                <div x-show="showReplies{{ $comment->id }}" x-collapse class="transition-all duration-600 mt-4">
                    @foreach ($comment->descendants as $descendant)
                        <div wire:key="descendant-container-{{ $descendant->id }}" class="p-6 mb-4 last:mb-0 bg-gray-50 dark:bg-gray-900 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none transition-all duration-600">
                            <livewire:comment.card wire:key="descendant-card-{{ $descendant->id }}" :comment="$descendant" />
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
