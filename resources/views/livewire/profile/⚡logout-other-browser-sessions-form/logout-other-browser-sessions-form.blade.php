<x-action-section>
    <x-slot:title>
        {{ __('Browser Sessions') }}
    </x-slot>

    <x-slot:description>
        {{ __('Manage and log out your active sessions on other browsers and devices.') }}
    </x-slot>

    <x-slot name="content">
        <flux:text class="max-w-xl">
            {{ __('If necessary, you may log out of all of your other browser sessions across all of your devices. Some of your recent sessions are listed below; however, this list may not be exhaustive. If you feel your account has been compromised, you should also update your password.') }}
        </flux:text>

        @if (count($this->sessions) > 0)
            <div class="mt-5 space-y-3">
                @foreach ($this->sessions as $session)
                    <div class="flex items-center gap-4 rounded-xl border border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 p-4">
                        <div class="shrink-0 text-zinc-500 dark:text-zinc-400">
                            @if ($session->is_desktop)
                                <flux:icon.computer-desktop class="size-8" />
                            @else
                                <flux:icon.device-phone-mobile class="size-8" />
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <flux:heading size="sm">
                                {{ $session->platform ?? __('Unknown') }} — {{ $session->browser ?? __('Unknown') }}
                            </flux:heading>
                            <flux:text size="xs">
                                {{ $session->ip_address }},
                                @if ($session->is_current_device)
                                    <span class="text-green-600 dark:text-green-500 font-semibold">{{ __('This device') }}</span>
                                @else
                                    {{ __('Last active') }} {{ $session->last_active }}
                                @endif
                            </flux:text>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex items-center mt-5">
            <flux:button
                variant="primary"
                size="sm"
                class="my-1.5 text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700"
                wire:click="confirmLogout"
                wire:loading.attr="disabled"
            >
                {{ __('Log Out Other Browser Sessions') }}
            </flux:button>
        </div>

        {{-- Log Out Other Devices Confirmation Modal --}}
        <flux:modal
            wire:model.live="confirmingLogout"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="arrow-right-start-on-rectangle"
                            class="w-8 h-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Log Out Other Browser Sessions') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Confirm your password to proceed') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4">
                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                        {{ __('Please enter your password to confirm you would like to log out of your other browser sessions across all of your devices.') }}
                    </flux:text>

                    <div
                        x-data="{}"
                        x-on:confirming-logout-other-browser-sessions.window="setTimeout(() => $refs.password.focus(), 250)"
                    >
                        <flux:input
                            type="password"
                            class="w-3/4"
                            autocomplete="current-password"
                            placeholder="{{ __('Password') }}"
                            x-ref="password"
                            wire:model="password"
                            wire:keydown.enter="logoutOtherBrowserSessions"
                        />
                        <flux:error name="password" />
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                    <flux:button
                        wire:click="$toggle('confirmingLogout')"
                        wire:loading.attr="disabled"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="logoutOtherBrowserSessions"
                        wire:loading.attr="disabled"
                        variant="primary"
                        size="sm"
                        icon="arrow-right-start-on-rectangle"
                        class="bg-red-600 hover:bg-red-700 text-white"
                    >
                        {{ __('Log Out Other Browser Sessions') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </x-slot>
</x-action-section>
