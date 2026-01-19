<x-layouts::base variant="simple">
    <x-slot:title>
        {{ __('Confirm your password') }}
    </x-slot>

    <x-slot:description>
        {{ __('Please confirm your password to continue.') }}
    </x-slot>

    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-300">
            {{ __('Please confirm your password to continue.') }}
        </div>

        <x-validation-errors class="mb-4" />

        <form
            method="POST"
            action="{{ route('password.confirm') }}"
        >
            @csrf

            <div>
                <x-label
                    for="password"
                    value="{{ __('Password') }}"
                />
                <x-input
                    id="password"
                    class="block mt-1 w-full"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
            </div>

            <x-honeypot />

            <div class="flex justify-end mt-4">
                <x-button class="ms-4">
                    {{ __('Confirm') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
    </x-guest-layout>
