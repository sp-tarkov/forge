<x-layouts::base>
    <x-slot:title>
        {{ __('Unsubscribed from Announcements') }}
    </x-slot>

    <x-slot:description>
        {{ __('You have been unsubscribed from announcement email notifications.') }}
    </x-slot>

    <x-slot:header>
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            {{ __('Unsubscribed') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="text-center">
                <div class="mb-6">
                    <flux:icon.check class="mx-auto h-16 w-16 text-green-500" />
                </div>

                <h3 class="text-xl font-semibold text-white mb-2">
                    {{ __('Successfully Unsubscribed') }}
                </h3>

                <p class="text-gray-400 mb-6">
                    {{ __('You will no longer receive announcement emails such as policy updates. You can re-enable them at any time from your notification preferences.') }}
                </p>

                <div class="flex justify-center gap-3">
                    <flux:button
                        variant="primary"
                        href="{{ route('profile.show') }}"
                    >
                        {{ __('Manage Email Preferences') }}
                    </flux:button>
                    <flux:button href="{{ route('mods') }}">
                        {{ __('Browse Mods') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
</x-layouts::base>
