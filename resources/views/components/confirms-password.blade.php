<span
    {{ $attributes->wire('then') }}
    x-data
    x-ref="span"
    x-on:click="$wire.startConfirmingPassword('{{ $confirmableId() }}')"
    x-on:password-confirmed.window="setTimeout(() => $event.detail.id === '{{ $confirmableId() }}' && $refs.span.dispatchEvent(new CustomEvent('then', { bubbles: false })), 250);"
>
    {{ $slot }}
</span>

@once
    <flux:modal
        wire:model.live="confirmingPassword"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="lock-closed"
                        class="h-8 w-8 text-blue-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ $title }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Security verification required') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-sm text-gray-300">
                    {{ $content }}
                </flux:text>

                <div
                    x-data="{}"
                    x-on:confirming-password.window="setTimeout(() => $refs.confirmable_password.focus(), 250)"
                >
                    <flux:input
                        type="password"
                        class="w-3/4"
                        placeholder="{{ __('Password') }}"
                        autocomplete="current-password"
                        x-ref="confirmable_password"
                        wire:model="confirmablePassword"
                        wire:keydown.enter="confirmPassword"
                    />
                    <flux:error name="confirmable_password" />
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                <flux:button
                    wire:click="stopConfirmingPassword"
                    wire:loading.attr="disabled"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    dusk="confirm-password-button"
                    wire:click="confirmPassword"
                    wire:loading.attr="disabled"
                    variant="primary"
                    size="sm"
                    icon="check"
                >
                    {{ $button }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
@endonce
