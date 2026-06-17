<x-layouts::base>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('OAuth Apps') }}
            </h2>

            <flux:button
                size="sm"
                x-data="{}"
                x-on:click="Livewire.dispatch('open-create-oauth-app')"
            >
                {{ __('Register new app') }}
            </flux:button>
        </div>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            @livewire('oauth.developer-portal')
        </div>
    </div>
</x-layouts::base>
