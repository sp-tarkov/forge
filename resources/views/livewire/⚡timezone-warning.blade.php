<?php

declare(strict_types=1);

use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showModal = false;

    public string $detectedTimezone = '';

    public string $fallbackTimezone = '';

    public bool $useJsDetection = true;

    public function openModal(): void
    {
        $this->loadFallbackTimezone();
        $this->showModal = true;
    }

    public function saveTimezone(): void
    {
        $timezoneToSave = $this->useJsDetection ? $this->detectedTimezone : $this->fallbackTimezone;

        if ($timezoneToSave) {
            Auth::user()->update(['timezone' => $timezoneToSave]);
            $this->showModal = false;

            // Flash success message
            flash()->success(__('Timezone updated successfully to :timezone', ['timezone' => $timezoneToSave]));

            $this->dispatch('$refresh');
        }
    }

    public function redirectToProfile(): void
    {
        $this->showModal = false;
        $this->redirect(route('profile.show'));
    }

    public function setDetectedTimezone(string $timezone): void
    {
        $this->detectedTimezone = $timezone;
    }

    #[Computed]
    public function shouldShowWarning(): bool
    {
        return Auth::check() && Auth::user()->timezone === null;
    }

    private function loadFallbackTimezone(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $latestEvent = TrackingEvent::query()->where('visitor_type', User::class)->where('visitor_id', $user->id)->whereNotNull('timezone')->latest('created_at')->first();

        $this->fallbackTimezone = $latestEvent?->timezone ?: 'UTC';
    }
};
?>

<div>
    @if ($this->shouldShowWarning)
        <div class="max-w-7xl mx-auto pb-6 px-4 gap-6 sm:px-6 lg:px-8">
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
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="clock"
                            class="w-8 h-8 text-blue-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-900 dark:text-gray-100"
                            >
                                {{ __('Auto-detect Timezone') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
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
                            <div
                                class="mt-2 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div
                                            class="text-sm font-medium text-gray-900 dark:text-white"
                                            id="detected-timezone-display"
                                        >
                                            <span x-show="!$wire.detectedTimezone">Detecting...</span>
                                            <span
                                                x-show="$wire.detectedTimezone"
                                                x-text="$wire.detectedTimezone"
                                            ></span>
                                        </div>
                                        @if ($fallbackTimezone && $fallbackTimezone !== 'UTC')
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
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
                <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                        <flux:icon
                            name="shield-check"
                            class="w-4 h-4 mr-2 flex-shrink-0"
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
