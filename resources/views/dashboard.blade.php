<x-layouts.base>
    <x-slot:title>
        {{ __('Your Dashboard - The Forge') }}
    </x-slot>

    <x-slot:description>
        {{ __('The dashboard for your account on the Forge.') }}
    </x-slot>

    <x-slot:header>
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    @livewire('timezone-warning')

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        @session('status')
            <flux:callout
                icon="check-circle"
                color="green"
                class="mb-6"
            >
                <flux:callout.text>{{ $value }}</flux:callout.text>
            </flux:callout>
        @endsession

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg">
                    <x-welcome />
                </div>

                @can('viewAny', App\Models\Report::class)
                    <div class="mt-6">
                        @livewire('report-centre')
                    </div>
                @endcan
            </div>

            <div class="lg:col-span-1">
                @livewire('notification-center')
            </div>
        </div>
    </div>
</x-layouts.base>
