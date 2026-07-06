<div
    class="align-top{{ !$canReportItem && !$showReportModal ? ' hidden' : '' }}{{ $variant === 'link' ? ' border-t border-gray-800 py-4 mt-4' : '' }} flex">
    @if ($canReportItem)
        @switch($variant)
            @case('link')
                <button
                    type="button"
                    x-on:click="$wire.showReportModal = true"
                    class="cursor-pointer text-sm text-slate-400 underline"
                >
                    {{ $this->buttonLabel }}
                </button>
            @break

            @case('comment')
                <button
                    type="button"
                    x-on:click="$wire.showReportModal = true"
                    class="cursor-pointer text-xs text-slate-400 hover:underline"
                >
                    {{ $this->buttonLabel }}
                </button>
            @break

            @default
                <flux:button
                    x-on:click="$wire.showReportModal = true"
                    variant="outline"
                    size="{{ $size }}"
                    icon="flag"
                    icon:variant="{{ $size === 'xs' ? 'micro' : 'micro' }}"
                >
                    {{ $this->buttonLabel }}
                </flux:button>
            @break
        @endswitch
    @endif

    <flux:modal
        name="report-modal"
        wire:model.self="showReportModal"
        class="md:w-[500px] lg:w-[600px]"
    >
        @if (!$submitted)
            {{-- Form Content --}}
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            name="flag"
                            class="h-8 w-8 text-red-600"
                        />
                        <div>
                            <flux:heading
                                size="xl"
                                class="text-gray-100"
                            >
                                {{ __('Report Content') }}
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-gray-400">
                                {{ __('Help us maintain a safe community') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-6">
                    <div class="rounded-lg border border-blue-800 bg-blue-900/20 p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon
                                name="information-circle"
                                class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500"
                            />
                            <div>
                                <flux:text class="text-sm font-medium text-blue-200">
                                    {{ __('Report Guidelines') }}
                                </flux:text>
                                <flux:text class="mt-1 text-sm text-blue-300">
                                    {{ __('Please select a reason for reporting this content. Reports help us maintain community standards.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <div>
                        <flux:radio.group
                            wire:model.live="reason"
                            label="{{ __('Reason for report') }}"
                            class="text-left"
                        >
                            @foreach (App\Enums\ReportReason::cases() as $reportReason)
                                <flux:radio
                                    value="{{ $reportReason->value }}"
                                    label="{{ $reportReason->label() }}"
                                />
                            @endforeach
                        </flux:radio.group>
                    </div>

                    <div>
                        <flux:textarea
                            wire:model.live="context"
                            label="{{ __('Additional details (optional)') }}"
                            placeholder="{{ __('Please provide any additional context that might help us review this report...') }}"
                            rows="4"
                        />
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
                            {{ __('Reports are reviewed by our moderation team') }}
                        </span>
                    </div>

                    <div class="flex gap-3">
                        <flux:button
                            wire:click="submit"
                            variant="primary"
                            size="sm"
                            icon="paper-airplane"
                        >
                            {{ __('Submit Report') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        @else
            {{-- Thank You Content --}}
            <div class="space-y-0">
                {{-- Header Section --}}
                <div class="mb-6 border-b border-gray-700 pb-6">
                    <div class="flex items-center justify-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-900/30">
                            <flux:icon
                                name="check"
                                class="h-6 w-6 text-green-400"
                            />
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Thank You') }}
                        </flux:heading>
                        <flux:text class="mt-2 text-gray-400">
                            {{ __('Your report helps keep our community safe') }}
                        </flux:text>
                    </div>
                </div>

                {{-- Content Section --}}
                <div class="space-y-4 text-center">
                    <div class="rounded-lg border border-green-800 bg-green-900/20 p-4">
                        <div class="flex items-start justify-center gap-3">
                            <div>
                                <flux:text class="text-sm font-medium text-green-200">
                                    {{ __('Report Submitted Successfully') }}
                                </flux:text>
                                <flux:text class="mt-1 text-sm text-green-300">
                                    {{ __('Our moderation team will review your report as soon as possible and take appropriate action if needed.') }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="mt-6 flex items-center justify-between border-t border-gray-700 pt-6">
                    <div class="flex items-center text-xs text-gray-400">
                        <flux:icon
                            name="clock"
                            class="mr-2 h-4 w-4 flex-shrink-0"
                        />
                        <span class="leading-tight">
                            {{ __('Typical review time: 24-48 hours') }}
                        </span>
                    </div>

                    <div class="flex gap-3">
                        <flux:button
                            x-on:click="$wire.showReportModal = false"
                            variant="primary"
                            size="sm"
                        >
                            {{ __('Close') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
