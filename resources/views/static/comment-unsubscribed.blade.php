<x-layouts::base>
    <x-slot:title>
        {{ __('Unsubscribed from Notifications') }}
    </x-slot>

    <x-slot:description>
        {{ __('You have been unsubscribed from comment notifications.') }}
    </x-slot>

    <x-slot:header>
        <h2 class="text-xl font-semibold leading-tight text-gray-100">
            {{ __('Unsubscribed') }}
        </h2>
    </x-slot>

    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-gray-900 p-6 shadow-xl sm:rounded-lg">
            <div class="text-center">
                <div class="mb-6">
                    <flux:icon.check class="mx-auto h-16 w-16 text-green-500" />
                </div>

                <h3 class="mb-2 text-xl font-semibold text-white">
                    {{ __('Successfully Unsubscribed') }}
                </h3>

                <p class="mb-6 text-gray-400">
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
</x-layouts::base>
