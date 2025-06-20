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
        <form class="flex-1" wire:submit="create">
            <flux:textarea
                name="body"
                wire:model="body"
                resize="vertical"
                placeholder="{{ __('Please ensure your comment does not break the community guidelines.') }}"
            />
            @error('body')
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
