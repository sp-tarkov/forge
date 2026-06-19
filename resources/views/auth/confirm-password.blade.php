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

        <form
            method="POST"
            action="{{ route('password.confirm') }}"
        >
            @csrf

            <flux:field>
                <flux:label for="password">{{ __('Password') }}</flux:label>
                <flux:input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <flux:error name="password" />
            </flux:field>

            <x-honeypot />

            <div class="flex justify-end mt-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Confirm') }}
                </flux:button>
            </div>
        </form>
    </x-authentication-card>
    </x-guest-layout>
