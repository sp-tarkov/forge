<x-layouts.base>
    <x-slot:title>
        {{ __('Your Dashboard - The Forge') }}
    </x-slot>

    <x-slot:description>
        {{ __('The dashboard for your account on The Forge.') }}
    </x-slot>

    <x-slot:header>
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    @if (auth()->user()->timezone === null)
        <div class="max-w-7xl mx-auto pb-6 px-4 gap-6 sm:px-6 lg:px-8">
            <flux:callout icon="exclamation-triangle" color="orange" inline="inline">
                <flux:callout.heading>Set Your Timezone</flux:callout.heading>
                <flux:callout.text>Please set your timezone in your profile to ensure that the correct time is displayed across the site.</flux:callout.text>
                <x-slot name="actions" class="@md:h-full m-0!">
                    <flux:button href="/user/profile">Edit Profile</flux:button>
                </x-slot>
            </flux:callout>
        </div>
    @endif

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg">
            <x-welcome />
        </div>
    </div>
</x-layouts.base>
