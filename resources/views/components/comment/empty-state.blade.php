@props(['isGuest' => false, 'commentable' => null])

<div class="mb-6 rounded-xl bg-gray-950 p-8 text-center shadow-md shadow-gray-950 drop-shadow-2xl">
    <div class="mb-6">
        <flux:icon
            name="chat-bubble-bottom-center-text"
            variant="outline"
            class="mx-auto size-16 text-gray-600"
        />
    </div>
    <h3 class="mb-2 text-xl font-semibold text-white">
        {{ __('No comments yet') }}
    </h3>
    <p class="{{ $isGuest ? 'mb-6' : '' }} text-gray-400">
        @if ($isGuest)
            {{ __('Be the first to share your thoughts! Login or register to join the discussion.') }}
        @else
            {{ __('Be the first to share your thoughts about this :item!', ['item' => $commentable->getCommentableDisplayName()]) }}
        @endif
    </p>
    @if ($isGuest)
        <div class="flex flex-col justify-center gap-3 sm:flex-row">
            <flux:button
                variant="primary"
                size="sm"
                class="bg-cyan-700 text-white hover:bg-cyan-600"
                href="{{ route('login') }}"
            >
                {{ __('Login') }}
            </flux:button>
            <flux:button
                variant="ghost"
                size="sm"
                class="text-gray-300 hover:bg-gray-800"
                href="{{ route('register') }}"
            >
                {{ __('Register') }}
            </flux:button>
        </div>
    @endif
</div>
