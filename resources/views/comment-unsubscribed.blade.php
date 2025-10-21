<x-layouts.base>
    <x-slot:title>
        {{ __('Unsubscribed from Notifications') }}
    </x-slot>

    <x-slot:description>
        {{ __('You have been unsubscribed from comment notifications.') }}
    </x-slot>

    <x-slot:header>
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('Unsubscribed') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="text-center">
                <div class="mb-6">
                    <flux:icon.check class="mx-auto h-16 w-16 text-green-500" />
                </div>

                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                    {{ __('Successfully Unsubscribed') }}
                </h3>

                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    {{ __('You will no longer receive email notifications for new comments on this ' . $commentable->getCommentableDisplayName() . '.') }}
                </p>

                <div class="flex justify-center">
                    <flux:button
                        variant="primary"
                        href="{{ route('mods') }}"
                    >
                        {{ __('Browse Mods') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
