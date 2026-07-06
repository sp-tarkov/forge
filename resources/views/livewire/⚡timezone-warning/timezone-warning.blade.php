<div>
    @if ($this->shouldShowWarning)
        <div class="mx-auto max-w-7xl gap-6 px-4 pb-6 sm:px-6 lg:px-8">
            <flux:callout
                icon="exclamation-triangle"
                color="orange"
                inline="inline"
            >
                <flux:callout.heading>Set Your Timezone</flux:callout.heading>
                <flux:callout.text>Please set your timezone in your profile to ensure that the correct time is displayed
                    across the site.</flux:callout.text>
                <x-slot
                    name="actions"
                    class="@md:h-full m-0!"
                >
                    <flux:button
                        wire:click="openModal"
                        variant="outline"
                        icon="clock"
                    >
                        Auto-detect & Save
                    </flux:button>
                </x-slot>
            </flux:callout>
        </div>

        <flux:modal
            wire:model.live="showModal"
            class="md:w-[500px] lg:w-[600px]"
        >
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="clock"
                            class="h-8 w-8 text-blue-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Auto-detect Timezone') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('We can automatically detect your timezone using your browser settings.') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-6">
                    {{-- Detected Timezone Display --}}
                    <div>
                        <flux:field>
                            <div class="mt-2 rounded-lg border border-gray-700 bg-gray-800 p-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div
                                            class="text-sm font-medium text-white"
                                            id="detected-timezone-display"
                                        >
                                            <span x-show="!$wire.detectedTimezone">Detecting...</span>
                                            <span
                                                x-show="$wire.detectedTimezone"
                                                x-text="$wire.detectedTimezone"
                                            ></span>
                                        </div>
                                        @if ($fallbackTimezone && $fallbackTimezone !== 'UTC')
                                            <div class="mt-1 text-xs text-gray-400">
                                                {{ __('Fallback from your activity: :timezone', ['timezone' => $fallbackTimezone]) }}
                                            </div>
                                        @endif
                                    </div>
                                    <flux:badge
                                        color="green"
                                        size="sm"
                                        x-show="$wire.detectedTimezone"
                                    >
                                        {{ __('Auto-detected') }}
                                    </flux:badge>
                                </div>
                            </div>
                        </flux:field>
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-between border-t border-gray-700 pt-6">
                    <div class="flex items-center text-xs text-gray-400">
                        <flux:icon
                            name="shield-check"
                            class="mr-2 h-4 w-4 flex-shrink-0"
                        />
                        <span class="leading-tight">
                            {{ __('Times will display in UTC until a timezone is set.') }}
                        </span>
                    </div>

                    <div class="flex gap-3">
                        <flux:button
                            wire:click="redirectToProfile"
                            variant="outline"
                            size="sm"
                        >
                            {{ __('Manually Set') }}
                        </flux:button>
                        <flux:button
                            wire:click="saveTimezone"
                            variant="primary"
                            size="sm"
                            icon="check"
                            x-bind:disabled="!$wire.detectedTimezone && !$wire.fallbackTimezone"
                        >
                            {{ __('Save') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>

        {{-- JavaScript for timezone detection --}}
        <div
            x-data="{
                init() {
                    this.$nextTick(() => {
                        if (typeof window.jstz !== 'undefined') {
                            const detectedTimezone = window.jstz.determine().name();
                            if (detectedTimezone) {
                                $wire.setDetectedTimezone(detectedTimezone);
                            }
                        }
                    });
                }
            }"
            x-init="init()"
            style="display: none;"
        ></div>
    @endif
</div>
