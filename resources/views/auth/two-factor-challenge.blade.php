<x-layouts::base variant="simple">
    <x-slot:title>
        {{ __('Two-Factor Authentication') }}
    </x-slot>

    <x-slot:description>
        {{ __('Two-factor authentication is a security measure that requires a second form of identification to access your account. This is to ensure that only you can access your account.') }}
    </x-slot>

    <x-authentication-card>
        <x-slot:logo>
            <x-authentication-card-logo />
        </x-slot>

        <div x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
            <div
                class="mb-4 text-sm text-gray-400"
                x-show="! recovery"
            >
                {{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}
            </div>

            <div
                class="mb-4 text-sm text-gray-400"
                x-cloak
                x-show="recovery"
            >
                {{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}
            </div>

            <form
                method="POST"
                action="{{ route('two-factor.login') }}"
            >
                @csrf

                <div
                    class="mt-4"
                    x-show="! recovery"
                >
                    <flux:otp
                        name="code"
                        length="6"
                        label="{{ __('Code') }}"
                        submit="auto"
                        autofocus
                        x-ref="code"
                    />
                    <flux:error name="code" />
                </div>

                <div
                    class="mt-4"
                    x-cloak
                    x-show="recovery"
                >
                    <flux:label for="recovery_code">{{ __('Recovery Code') }}</flux:label>
                    <flux:input
                        id="recovery_code"
                        type="text"
                        name="recovery_code"
                        x-ref="recovery_code"
                        autocomplete="one-time-code"
                    />
                    <flux:error name="recovery_code" />
                </div>

                <div class="mt-4 flex items-center justify-end">
                    <button
                        type="button"
                        class="cursor-pointer text-sm text-gray-400 underline hover:text-gray-100"
                        x-show="! recovery"
                        x-on:click="
                                        recovery = true;
                                        $nextTick(() => { $refs.recovery_code.focus() })
                                    "
                    >
                        {{ __('Use a recovery code') }}
                    </button>

                    <button
                        type="button"
                        class="cursor-pointer text-sm text-gray-400 underline hover:text-gray-100"
                        x-cloak
                        x-show="recovery"
                        x-on:click="
                                        recovery = false;
                                        $nextTick(() => { $refs.code.focus() })
                                    "
                    >
                        {{ __('Use an authentication code') }}
                    </button>

                    <x-honeypot />

                    <flux:button
                        variant="primary"
                        type="submit"
                    >
                        {{ __('Log in') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </x-authentication-card>
    </x-guest-layout>
