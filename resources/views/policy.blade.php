<x-guest-layout>
    <x-slot:title>
        {{ __('Privacy Policy - The Forge') }}
    </x-slot>

    <x-slot:description>
        {{ __('The privacy policy for The Forge.') }}
    </x-slot>

    <x-slot:header></x-slot>

    <div class="pt-4 bg-gray-100">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0">
            <div>
                <x-authentication-card-logo />
            </div>

            <div class="w-full sm:max-w-2xl mt-6 p-6 bg-white shadow-md overflow-hidden sm:rounded-lg prose">
                {!! $policy !!}
            </div>
        </div>
    </div>
</x-guest-layout>
