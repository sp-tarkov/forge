<x-action-section>
    <x-slot:title>
        {{ __('Delete Account') }}
    </x-slot>

    <x-slot:description>
        {{ __('Permanently delete your account.') }}
    </x-slot>

    <x-slot name="content">
        <flux:text class="max-w-xl">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </flux:text>

        <div class="mt-5">
            <flux:button
                variant="danger"
                size="sm"
                wire:click="confirmUserDeletion"
                wire:loading.attr="disabled"
            >
                {{ __('Delete Account') }}
            </flux:button>
        </div>

        <!-- Delete User Confirmation Modal -->
        <flux:modal
            wire:model.live="confirmingUserDeletion"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="trash"
                            class="h-8 w-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Delete Account') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-red-400">
                                {{ __('This action is irreversible') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <div class="rounded-lg border border-red-700 bg-red-950/30 p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon
                                name="exclamation-triangle"
                                class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600"
                            />
                            <div>
                                <flux:text class="text-sm font-medium text-red-200">
                                    {{ __('Warning!') }}
                                </flux:text>
                                <flux:text class="mt-1 text-sm text-red-300">
                                    {{ __('Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <flux:text class="text-sm text-gray-300">
                        {{ __('Please enter your password to confirm you would like to permanently delete your account.') }}
                    </flux:text>

                    <div
                        x-data="{}"
                        x-on:confirming-delete-user.window="setTimeout(() => $refs.password.focus(), 250)"
                    >
                        <flux:input
                            type="password"
                            class="w-3/4"
                            autocomplete="current-password"
                            placeholder="{{ __('Password') }}"
                            x-ref="password"
                            wire:model="password"
                            wire:keydown.enter="deleteUser"
                        />
                        <flux:error name="password" />
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-between border-t border-gray-700 pt-6">
                    <div class="flex items-center text-xs text-red-400">
                        <flux:icon
                            name="shield-exclamation"
                            class="mr-2 h-4 w-4 flex-shrink-0"
                        />
                        <span class="leading-tight">
                            {{ __('Permanent deletion') }}
                        </span>
                    </div>

                    <div class="flex gap-3">
                        <flux:button
                            wire:click="$toggle('confirmingUserDeletion')"
                            wire:loading.attr="disabled"
                            variant="outline"
                            size="sm"
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button
                            wire:click="deleteUser"
                            wire:loading.attr="disabled"
                            variant="primary"
                            size="sm"
                            icon="trash"
                            class="bg-red-600 text-white hover:bg-red-700"
                        >
                            {{ __('Delete Account') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>
    </x-slot>
</x-action-section>
