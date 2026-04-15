<x-form-section submit="updatePassword">
    <x-slot:title>
        {{ __('Update Password') }}
    </x-slot>

    <x-slot:description>
        {{ __('Ensure your account is using a long, random password to stay secure.') }}
    </x-slot>

    <x-slot name="form">

        @if (auth()->user()->password === null)
            <div class="col-span-6 sm:col-span-4 mt-3 max-w-xl text-sm text-gray-600 dark:text-gray-400">
                <p>{{ __('Your account does not have a password set. We recommend setting a password so that you can recover your account if you need to.') }}
                </p>
            </div>
        @else
            <div class="col-span-6 sm:col-span-4">
                <flux:field>
                    <flux:label for="current_password">{{ __('Current Password') }}</flux:label>
                    <flux:input
                        id="current_password"
                        type="password"
                        wire:model="state.current_password"
                        autocomplete="off"
                    />
                    <flux:error name="current_password" />
                </flux:field>
            </div>
        @endif

        <div class="col-span-6 sm:col-span-4">
            <flux:field>
                <flux:label for="password">{{ __('New Password') }}</flux:label>
                <flux:input
                    id="password"
                    type="password"
                    wire:model="state.password"
                    autocomplete="off"
                />
                <flux:error name="password" />
            </flux:field>
        </div>

        <div class="col-span-6 sm:col-span-4">
            <flux:field>
                <flux:label for="password_confirmation">{{ __('Confirm Password') }}</flux:label>
                <flux:input
                    id="password_confirmation"
                    type="password"
                    wire:model="state.password_confirmation"
                    autocomplete="off"
                />
                <flux:error name="password_confirmation" />
            </flux:field>
        </div>
    </x-slot>

    <x-slot name="actions">
        <flux:button type="submit" variant="primary">
            {{ __('Save') }}
        </flux:button>
    </x-slot>
</x-form-section>
