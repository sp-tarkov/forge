<x-action-section>
    <x-slot:title>
        {{ __('Delete Account') }}
    </x-slot>

    <x-slot:description>
        {{ __('Permanently delete your account.') }}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl text-sm text-gray-600 dark:text-gray-400">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </div>

        <div class="mt-5">
            <x-danger-button wire:click="confirmUserDeletion" wire:loading.attr="disabled">
                {{ __('Delete Account') }}
            </x-danger-button>
        </div>

        <!-- Delete User Confirmation Modal -->
        <flux:modal wire:model.live="confirmingUserDeletion" class="md:w-[500px] lg:w-[600px]">
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon name="trash" class="w-8 h-8 text-red-600" />
                        <div>
                            <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                                {{ __('Delete Account') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-red-600 dark:text-red-400 text-sm">
                                {{ __('This action is irreversible') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <div class="bg-red-50 dark:bg-red-950/30 border border-red-300 dark:border-red-700 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <flux:text class="text-red-900 dark:text-red-200 text-sm font-medium">
                                    {{ __('Warning!') }}
                                </flux:text>
                                <flux:text class="text-red-800 dark:text-red-300 text-sm mt-1">
                                    {{ __('Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('Please enter your password to confirm you would like to permanently delete your account.') }}
                    </flux:text>

                    <div x-data="{}" x-on:confirming-delete-user.window="setTimeout(() => $refs.password.focus(), 250)">
                        <flux:input type="password" class="w-3/4"
                                    autocomplete="current-password"
                                    placeholder="{{ __('Password') }}"
                                    x-ref="password"
                                    wire:model="password"
                                    wire:keydown.enter="deleteUser" />
                        <x-input-error for="password" class="mt-2" />
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center text-xs text-red-600 dark:text-red-400">
                        <flux:icon name="shield-exclamation" class="w-4 h-4 mr-2 flex-shrink-0" />
                        <span class="leading-tight">
                            {{ __('Permanent deletion') }}
                        </span>
                    </div>

                    <div class="flex gap-3">
                        <flux:button wire:click="$toggle('confirmingUserDeletion')" wire:loading.attr="disabled" variant="outline" size="sm">
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button wire:click="deleteUser" wire:loading.attr="disabled" variant="primary" size="sm" icon="trash" class="bg-red-600 hover:bg-red-700 text-white">
                            {{ __('Delete Account') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>
    </x-slot>
</x-action-section>
