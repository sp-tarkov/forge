<x-layouts.base>
    <x-slot:title>
        {{ __('Report Centre - The Forge') }}
    </x-slot>

    <x-slot:description>
        {{ __('Manage and review user reports.') }}
    </x-slot>

    <x-slot:header>
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('Report Centre') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        @livewire('report-centre')
    </div>
</x-layouts.base>
