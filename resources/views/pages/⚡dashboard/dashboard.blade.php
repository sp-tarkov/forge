<x-slot:title>
    {{ __('Your Dashboard - The Forge') }}
</x-slot>

<x-slot:description>
    {{ __('The dashboard for your account on the Forge.') }}
</x-slot>

<x-slot:header>
    <h2 class="text-xl font-semibold leading-tight text-gray-100">
        {{ __('Dashboard') }}
    </h2>
</x-slot>

<div>
    <livewire:timezone-warning />

    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        @session('status')
            <flux:callout
                icon="check-circle"
                color="green"
                class="mb-6"
            >
                <flux:callout.text>{{ $value }}</flux:callout.text>
            </flux:callout>
        @endsession

        <div class="overflow-hidden bg-gray-900 shadow-xl sm:rounded-lg">
            <x-welcome />
        </div>
    </div>
</div>
