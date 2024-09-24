@props(['isFollowing'])

<form>
    @if ($isFollowing)
        {{-- Following button --}}
        <x-profile-button wire:click="unfollow">
            <x-slot:icon>
                <svg class="-ml-0.5 mr-1.5 h-5 w-5 text-red-400 dark:text-red-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="m12.82 5.58-.82.822-.824-.824a5.375 5.375 0 1 0-7.601 7.602l7.895 7.895a.75.75 0 0 0 1.06 0l7.902-7.897a5.376 5.376 0 0 0-.001-7.599 5.38 5.38 0 0 0-7.611 0Z" />
                </svg>
            </x-slot:icon>
            {{  __('Following') }}
        </x-profile-button>
    @else
        {{-- Follow button --}}
        <x-profile-button wire:click="follow">
            <x-slot:icon>
                <svg class="-ml-0.5 mr-1.5 h-5 w-5 text-gray-400 dark:text-gray-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="m12.82 5.58-.82.822-.824-.824a5.375 5.375 0 1 0-7.601 7.602l7.895 7.895a.75.75 0 0 0 1.06 0l7.902-7.897a5.376 5.376 0 0 0-.001-7.599 5.38 5.38 0 0 0-7.611 0Zm6.548 6.54L12 19.485 4.635 12.12a3.875 3.875 0 1 1 5.48-5.48l1.358 1.357a.75.75 0 0 0 1.073-.012L13.88 6.64a3.88 3.88 0 0 1 5.487 5.48Z"/>
                </svg>
            </x-slot:icon>
            {{ __('Follow') }}
        </x-profile-button>
    @endif
</form>
