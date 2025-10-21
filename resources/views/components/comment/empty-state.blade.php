@props(['isGuest' => false, 'commentable' => null])

<div class="p-8 mb-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl text-center">
    <div class="mb-6">
        <flux:icon
            name="chat-bubble-bottom-center-text"
            variant="outline"
            class="mx-auto size-16 text-gray-400 dark:text-gray-600"
        />
    </div>
    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
        {{ __('No comments yet') }}
    </h3>
    <p class="text-gray-600 dark:text-gray-400 {{ $isGuest ? 'mb-6' : '' }}">
        @if ($isGuest)
            {{ __('Be the first to share your thoughts! Login or register to join the discussion.') }}
        @else
            {{ __('Be the first to share your thoughts about this :item!', ['item' => $commentable->getCommentableDisplayName()]) }}
        @endif
    </p>
    @if ($isGuest)
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <flux:button
                variant="primary"
                size="sm"
                class="text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700"
                href="{{ route('login') }}"
            >
                {{ __('Login') }}
            </flux:button>
            <flux:button
                variant="ghost"
                size="sm"
                class="text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                href="{{ route('register') }}"
            >
                {{ __('Register') }}
            </flux:button>
        </div>
    @endif
</div>
