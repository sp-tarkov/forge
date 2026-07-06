<x-layouts::base variant="simple">
    <x-slot:title>
        {{ __('Terms of Service - The Forge') }}
    </x-slot>

    <x-slot:description>
        {{ __('The terms of service for the Forge.') }}
    </x-slot>

    <x-slot:header></x-slot>

    <div class="bg-gray-100 pt-4">
        <div class="flex min-h-screen flex-col items-center pt-6 sm:pt-0">
            <div>
                <x-authentication-card-logo />
            </div>

            <div class="prose mt-6 w-full overflow-hidden bg-white p-6 shadow-md sm:max-w-2xl sm:rounded-lg">
                {!! $terms !!}
            </div>
        </div>
    </div>
</x-layouts::base>
