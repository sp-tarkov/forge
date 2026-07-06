<x-layouts::base variant="simple">
    <x-slot:title>
        {{ __('Reset your password') }}
    </x-slot>

    <x-slot:description>
        {{ __('Reset your Forge password by using the form below.') }}
    </x-slot>

    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <form
            method="POST"
            action="{{ route('password.update') }}"
        >
            @csrf

            <input
                type="hidden"
                name="token"
                value="{{ $request->route('token') }}"
            >

            <div class="space-y-4">
                <flux:field>
                    <flux:label for="email">{{ __('Email') }}</flux:label>
                    <flux:input
                        id="email"
                        type="email"
                        name="email"
                        :value="old('email', $request->email)"
                        required
                        autofocus
                        autocomplete="username"
                    />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label for="password">{{ __('Password') }}</flux:label>
                    <flux:input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                    />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label for="password_confirmation">{{ __('Confirm Password') }}</flux:label>
                    <flux:input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                    />
                    <flux:error name="password_confirmation" />
                </flux:field>
            </div>

            <x-honeypot />

            <div class="mt-4 flex items-center justify-end">
                <flux:button
                    variant="primary"
                    type="submit"
                >
                    {{ __('Reset Password') }}
                </flux:button>
            </div>
        </form>
    </x-authentication-card>
    </x-guest-layout>
