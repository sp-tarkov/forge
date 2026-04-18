<x-form-section submit="updatePassword">
    <x-slot:title>
        {{ __('Update Password') }}
    </x-slot>

    <x-slot:description>
        {{ __('Ensure your account is using a long, random password to stay secure.') }}
    </x-slot>

    <x-slot name="form">
        @if (auth()->user()->password === null)
            <div class="col-span-6">
                <flux:callout
                    icon="information-circle"
                    color="sky"
                    inline
                >
                    <flux:callout.text>
                        {{ __('Your account does not have a password set. We recommend setting a password so that you can recover your account if you need to.') }}
                    </flux:callout.text>
                </flux:callout>
            </div>
        @else
            <flux:field class="col-span-6">
                <flux:label>{{ __('Current Password') }}</flux:label>
                <flux:description>
                    {{ __('Enter your existing password to confirm the change.') }}
                </flux:description>
                <flux:input
                    id="current_password"
                    type="password"
                    wire:model="state.current_password"
                    autocomplete="off"
                />
                <flux:error name="current_password" />
            </flux:field>
        @endif

        <flux:field class="col-span-6">
            <flux:label>{{ __('New Password') }}</flux:label>
            <flux:description>
                {{ __('Must be at least 12 characters and not found in known data breaches.') }}
            </flux:description>
            <flux:input
                id="password"
                type="password"
                wire:model="state.password"
                autocomplete="off"
            />
            <flux:error name="password" />
        </flux:field>

        <flux:field class="col-span-6">
            <flux:label>{{ __('Confirm Password') }}</flux:label>
            <flux:description>
                {{ __('Re-enter your new password to confirm it.') }}
            </flux:description>
            <flux:input
                id="password_confirmation"
                type="password"
                wire:model="state.password_confirmation"
                autocomplete="off"
            />
            <flux:error name="password_confirmation" />
        </flux:field>
    </x-slot>

    <x-slot name="actions">
        <flux:button
            type="submit"
            variant="primary"
            size="sm"
            class="my-1.5 text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700"
        >
            {{ __('Save') }}
        </flux:button>
    </x-slot>
</x-form-section>
