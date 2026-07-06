<x-action-section>
    <x-slot:title>
        {{ __('Two Factor Authentication') }}
    </x-slot>

    <x-slot:description>
        {{ __('Add additional security to your account using two factor authentication.') }}
    </x-slot>

    <x-slot name="content">
        <flux:heading size="lg">
            @if ($this->enabled)
                @if ($showingConfirmation)
                    {{ __('Finish enabling two factor authentication.') }}
                @else
                    {{ __('You have enabled two factor authentication.') }}
                @endif
            @else
                {{ __('You have not enabled two factor authentication.') }}
            @endif
        </flux:heading>

        <flux:text class="mt-3 max-w-xl">
            {{ __('When two factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may retrieve this token from your phone\'s Google Authenticator application.') }}
        </flux:text>

        @if ($this->enabled)
            @if ($showingQrCode)
                <flux:text class="mt-4 max-w-xl font-semibold">
                    @if ($showingConfirmation)
                        {{ __('To finish enabling two factor authentication, scan the following QR code using your phone\'s authenticator application or enter the setup key and provide the generated OTP code.') }}
                    @else
                        {{ __('Two factor authentication is now enabled. Scan the following QR code using your phone\'s authenticator application or enter the setup key.') }}
                    @endif
                </flux:text>

                <div class="mt-4 inline-block rounded-xl border border-white/10 bg-white p-4">
                    {!! $this->user->twoFactorQrCodeSvg() !!}
                </div>

                <flux:text class="mt-4 max-w-xl font-semibold">
                    {{ __('Setup Key') }}: <span class="font-mono">{{ decrypt($this->user->two_factor_secret) }}</span>
                </flux:text>

                @if ($showingConfirmation)
                    <div class="mt-4">
                        <flux:otp
                            wire:model="code"
                            length="6"
                            label="{{ __('Code') }}"
                            submit="auto"
                            autofocus
                        />
                        <flux:error
                            name="code"
                            class="mt-2"
                        />
                    </div>
                @endif
            @endif

            @if ($showingRecoveryCodes)
                <flux:text class="mt-4 max-w-xl font-semibold">
                    {{ __('Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two factor authentication device is lost.') }}
                </flux:text>

                <div class="mt-4 grid gap-1 max-w-xl rounded-xl border border-white/10 bg-white/5 p-4 font-mono text-sm text-zinc-300">
                    @foreach (json_decode(decrypt($this->user->two_factor_recovery_codes), true) as $code)
                        <div>{{ $code }}</div>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="mt-5 flex flex-wrap gap-3">
            @if (! $this->enabled)
                <x-confirms-password wire:then="enableTwoFactorAuthentication">
                    <flux:button
                        variant="primary"
                        size="sm"
                        type="button"
                        class="my-1.5 text-white hover:bg-cyan-600 bg-cyan-700"
                        wire:loading.attr="disabled"
                    >
                        {{ __('Enable') }}
                    </flux:button>
                </x-confirms-password>
            @else
                @if ($showingRecoveryCodes)
                    <x-confirms-password wire:then="regenerateRecoveryCodes">
                        <flux:button
                            variant="outline"
                            size="sm"
                        >
                            {{ __('Regenerate Recovery Codes') }}
                        </flux:button>
                    </x-confirms-password>
                @elseif ($showingConfirmation)
                    <x-confirms-password wire:then="confirmTwoFactorAuthentication">
                        <flux:button
                            variant="primary"
                            size="sm"
                            type="button"
                            class="my-1.5 text-white hover:bg-cyan-600 bg-cyan-700"
                            wire:loading.attr="disabled"
                        >
                            {{ __('Confirm') }}
                        </flux:button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="showRecoveryCodes">
                        <flux:button
                            variant="outline"
                            size="sm"
                        >
                            {{ __('Show Recovery Codes') }}
                        </flux:button>
                    </x-confirms-password>
                @endif

                @if ($showingConfirmation)
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <flux:button
                            variant="outline"
                            size="sm"
                            wire:loading.attr="disabled"
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <flux:button
                            variant="danger"
                            size="sm"
                            wire:loading.attr="disabled"
                        >
                            {{ __('Disable') }}
                        </flux:button>
                    </x-confirms-password>
                @endif
            @endif
        </div>
    </x-slot>
</x-action-section>
