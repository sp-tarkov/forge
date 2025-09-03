<div>
    @if ($user->isBanned())
        <flux:button x-on:click="$wire.showUnbanModal = true" variant="outline" size="sm" class="whitespace-nowrap">
            <div class="flex items-center">
                <flux:icon.shield-check class="text-green-600 mr-1.5" />
                {{ __('Unban User') }}
            </div>
        </flux:button>
    @else
        <flux:button x-on:click="$wire.showBanModal = true" variant="outline" size="sm" class="whitespace-nowrap">
            <div class="flex items-center">
                <flux:icon.shield-exclamation class="text-red-600 mr-1.5" />
                {{ __('Ban User') }}
            </div>
        </flux:button>
    @endif

    {{-- Ban Modal --}}
    <flux:modal name="ban-modal" wire:model.self="showBanModal" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="shield-exclamation" class="w-8 h-8 text-red-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Ban User') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Restrict user access to the platform') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-6">
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" />
                        <div>
                            <flux:text class="text-red-800 dark:text-red-200 text-sm font-medium">
                                {{ __('Warning') }}
                            </flux:text>
                            <flux:text class="text-red-700 dark:text-red-300 text-sm mt-1">
                                {{ __('Banned users cannot access the platform when logged in, but may still access content when logged out.') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                <div>
                    <flux:radio.group wire:model.live="duration" label="{{ __('Ban Duration') }}" class="text-left">
                        @foreach ($this->getDurationOptions() as $value => $label)
                            <flux:radio value="{{ $value }}" label="{{ $label }}" />
                        @endforeach
                    </flux:radio.group>
                </div>

                <div>
                    <flux:textarea
                        wire:model.live="reason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Please provide a reason for this ban...') }}"
                        rows="3"
                    />
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                    <flux:icon name="information-circle" class="w-4 h-4 mr-2 flex-shrink-0" />
                    <span class="leading-tight">
                        {{ __('This action can be reversed by unbanning the user') }}
                    </span>
                </div>

                <div class="flex gap-3">
                    <flux:button x-on:click="$wire.showBanModal = false" variant="outline" size="sm">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button wire:click="ban" variant="danger" size="sm" icon="shield-exclamation">
                        {{ __('Ban User') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- Unban Modal --}}
    <flux:modal name="unban-modal" wire:model.self="showUnbanModal" class="md:w-[400px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="shield-check" class="w-8 h-8 text-green-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Unban User') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Restore user access to the platform') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300">
                    {{ __('Are you sure you want to unban this user? They will regain full access to the platform.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <flux:button x-on:click="$wire.showUnbanModal = false" variant="outline" size="sm">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button wire:click="unban" variant="primary" size="sm" icon="shield-check">
                    {{ __('Unban User') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
